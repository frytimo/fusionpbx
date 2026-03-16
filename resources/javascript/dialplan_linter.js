/*
 * FusionPBX
 * Version: MPL 1.1
 *
 * The contents of this file are subject to the Mozilla Public License Version
 * 1.1 (the "License"); you may not use this file except in compliance with
 * the License. You may obtain a copy of the License at
 * http://www.mozilla.org/MPL/
 *
 * Software distributed under the License is distributed on an "AS IS" basis,
 * WITHOUT WARRANTY OF ANY KIND, either express or implied. See the License
 * for the specific language governing rights and limitations under the
 * License.
 *
 * The Original Code is FusionPBX
 *
 * The Initial Developer of the Original Code is
 * Mark J Crane <markjcrane@fusionpbx.com>
 * Portions created by the Initial Developer are Copyright (C) 2008-2026
 * the Initial Developer. All Rights Reserved.
 *
 * Contributor(s):
 * Mark J Crane <markjcrane@fusionpbx.com>
 */

/**
 * DialplanLinter
 *
 * Runs an array of lint rules against a parsed dialplan tree and returns a
 * flat array of findings.
 *
 * Usage:
 *   var findings = DialplanLinter.run(tree, DialplanLintRules);
 *
 * Each finding: { node, severity, ruleId, message }
 *   node     — reference to the tree node object
 *   severity — 'error' | 'warning' | 'info'
 *   ruleId   — string identifier of the rule that produced it
 *   message  — human-readable description of the problem
 *
 * Rules format (see dialplan_lint_rules.js):
 *   {
 *     id:          string
 *     severity:    'error' | 'warning' | 'info'
 *     description: string
 *     check:       function(tree) -> [ { node, message }, ... ]
 *   }
 */
var DialplanLinter = (function () {
    'use strict';

    /**
     * Run all rules against a tree and return a flat findings array.
     *
     * @param {object}   tree  - parsed tree from DialplanParser.parseXmlToTree
     * @param {Array}    rules - array of rule objects
     * @returns {Array}  findings
     */
    function run(tree, rules) {
        var findings = [];

        if (!tree || !rules || !rules.length) {
            return findings;
        }

        for (var i = 0; i < rules.length; i++) {
            var rule = rules[i];
            if (!rule || typeof rule.check !== 'function') {
                continue;
            }
            try {
                var results = rule.check(tree);
                if (results && results.length) {
                    for (var j = 0; j < results.length; j++) {
                        var r = results[j];
                        if (r && r.node) {
                            findings.push({
                                node:     r.node,
                                severity: rule.severity || 'info',
                                ruleId:   rule.id       || 'unknown',
                                message:  r.message     || rule.description || ''
                            });
                        }
                    }
                }
            } catch (e) {
                // A broken rule must never crash the editor
            }
        }

        return findings;
    }

    return { run: run };

}());
