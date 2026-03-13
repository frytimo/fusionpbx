<?php

/*
 * FusionPBX — xml_cdr pipeline test: modifier_xml_sanitize
 *
 * Tests: valid XML passes; empty/invalid content → discard exception;
 *        URL-encoded XML gets decoded and stored via set_raw_content().
 */

test_runner::add_test('sanitize: valid XML passes unmodified', function () {
	$record   = record_from_fixture('b_inbound_answered.cdr.xml');
	$settings = new settings();
	$mod      = new modifier_xml_sanitize();

	// Should not throw
	$mod($settings, $record);

	// Parsed element must still be accessible
	assert_not_null($record->parsed());
});

test_runner::add_test('sanitize: empty content throws discard exception', function () {
	$record = xml_cdr_record::from_xml('', '/tmp/empty.cdr.xml');
	$mod    = new modifier_xml_sanitize();

	assert_exception(
		function () use ($record, $mod) {
			$mod(new settings(), $record);
		},
		xml_cdr_discard_exception::class
	);
});

test_runner::add_test('sanitize: invalid XML throws discard exception', function () {
	$record = record_from_fixture('invalid_xml.cdr.xml');
	$mod    = new modifier_xml_sanitize();

	assert_exception(
		function () use ($record, $mod) {
			$mod(new settings(), $record);
		},
		xml_cdr_discard_exception::class
	);
});

test_runner::add_test('sanitize: URL-encoded XML gets decoded and re-stored', function () {
	// Build a tiny valid CDR XML and URL-encode it
	$xml  = '<cdr><variables><uuid>1234</uuid></variables></cdr>';
	$encoded = urlencode($xml);

	// Construction requires raw content starting with '%'
	$record = xml_cdr_record::from_xml($encoded, '/tmp/b_encoded.cdr.xml');
	$mod    = new modifier_xml_sanitize();

	$mod(new settings(), $record);

	// After modify the raw content should be the decoded XML, not the encoded form
	// We can verify indirectly by ensuring parsed() works and returns the expected uuid
	$parsed = $record->parsed();
	assert_not_null($parsed);
	assert_equals('1234', (string) $parsed->variables->uuid);
});

test_runner::add_test('sanitize: whitespace-only content throws discard exception', function () {
	$record = xml_cdr_record::from_xml("   \n\t  ", '/tmp/whitespace.cdr.xml');
	$mod    = new modifier_xml_sanitize();

	assert_exception(
		function () use ($record, $mod) {
			$mod(new settings(), $record);
		},
		xml_cdr_discard_exception::class
	);
});

test_runner::add_test('sanitize: mod_limit wildcard tag names are stripped and CDR parses cleanly', function () {
	// Fixture contains two invalid-name leaf elements produced by FreeSWITCH
	// mod_limit when the destination is a voicemail transfer address (*992110):
	//   <limit_usage_wisecounty.encoretg.net_*992110>1</…>
	//   <limit_usage_domain_*992110>1</…>
	// These have '*' in the tag name, which is illegal in XML.
	$record   = record_from_fixture('b_limit_var_invalid_name.cdr.xml');
	$settings = new settings();
	$mod      = new modifier_xml_sanitize();

	// Must NOT throw — the repair pass should strip the offending elements.
	$mod($settings, $record);

	// The record must still be parseable and yield the expected uuid.
	$parsed = $record->parsed();
	assert_not_null($parsed);
	assert_equals('b9b9b9b9-0000-0000-0000-000000000099', (string) $parsed->variables->uuid);

	// The invalid elements must have been removed from the stored raw content.
	$raw = $record->get_raw_content();
	assert_equals(false, strpos($raw, 'limit_usage_wisecounty.encoretg.net_*992110'),
		'Repaired raw content still contains the illegal tag name');
});

test_runner::add_test('sanitize: truly malformed XML (unclosed tag) still throws discard exception', function () {
	$broken = '<cdr><variables><uuid>abc</uuid></variables>';  // missing </cdr>
	$record = xml_cdr_record::from_xml($broken, '/tmp/broken.cdr.xml');
	$mod    = new modifier_xml_sanitize();

	assert_exception(
		function () use ($record, $mod) {
			$mod(new settings(), $record);
		},
		xml_cdr_discard_exception::class
	);
});

// One test per character that is illegal in an XML Name but that commonly
// appears inside FreeSWITCH channel variable names written by modules such as
// mod_limit (e.g.  limit_usage_domain_*992110).
//
// Characters deliberately excluded from this list:
//   <  >  "  '  &  /  (whitespace)
// because they would corrupt the enclosing XML structure rather than merely
// producing an invalid element name, and are therefore handled by a different
// failure mode.
$invalid_tag_chars = [
	'*'  => 'asterisk',           // mod_limit + voicemail transfer address (*992110) — the original bug
	'@'  => 'at-sign',            // SIP URI host-part separators
	'!'  => 'exclamation-mark',   // various dialplan patterns
	'#'  => 'hash',               // DTMF codes / SIP URI fragments
	'$'  => 'dollar-sign',        // FreeSWITCH inline variable expansion tokens
	'%'  => 'percent-sign',       // URL-encoded remnants in variable values
	'('  => 'left-parenthesis',   // phone-number formatting  e.g. (800)
	')'  => 'right-parenthesis',  // phone-number formatting
	'+'  => 'plus-sign',          // E.164 international prefix (+1…)
	'='  => 'equals-sign',        // SIP URI parameter separators
	'['  => 'left-bracket',       // IPv6 literals in SIP URIs
	']'  => 'right-bracket',      // IPv6 literals in SIP URIs
	'?'  => 'question-mark',      // SIP URI query string
	','  => 'comma',              // SIP multi-value header parameters
	'~'  => 'tilde',              // various protocol tokens
	'|'  => 'pipe',               // dialplan pipe/inline action separators
	'\\' => 'backslash',          // Windows-style path remnants
	'{'  => 'left-brace',         // template / placeholder tokens
	'}'  => 'right-brace',        // template / placeholder tokens
];

foreach ($invalid_tag_chars as $char => $label) {
	test_runner::add_test(
		"sanitize: illegal char '{$label}' in tag name is stripped and CDR parses cleanly",
		function () use ($char, $label) {
			// Build a minimal CDR XML that is otherwise valid except for one
			// leaf element whose tag name contains the offending character.
			$tag = 'limit_usage_domain_' . $char . '92110';
			$xml = '<?xml version="1.0"?>'
				. '<cdr><variables>'
				. '<uuid>sanitize-illegal-char-test</uuid>'
				. '<' . $tag . '>1</' . $tag . '>'
				. '</variables></cdr>';

			$record = xml_cdr_record::from_xml($xml, '/tmp/illegal_char_' . $label . '.cdr.xml');
			$mod    = new modifier_xml_sanitize();

			// Must NOT throw — repaired by stripping the offending element.
			$mod(new settings(), $record);

			$parsed = $record->parsed();
			assert_not_null($parsed, "parsed() is null after repairing tag containing '{$label}'");
			assert_equals(
				'sanitize-illegal-char-test',
				(string) $parsed->variables->uuid,
				"uuid field lost after repairing tag containing '{$label}'"
			);

			// The offending element must have been removed from the raw content.
			assert_false(
				strpos($record->get_raw_content(), '<' . $tag . '>') !== false,
				"Repaired raw content still contains the element with illegal char '{$label}'"
			);
		}
	);
}
