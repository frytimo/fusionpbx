<?php
abstract class render_music_on_hold implements music_on_hold_hooks {

public static function on_pre_save(url $url, array &$data): void {
}

public static function on_post_save(url $url, array $data): void {
}

public static function on_pre_action(url $url, string &$action, array &$items): void {
}

public static function on_post_action(url $url, string $action, array $items): void {
}

public static function on_pre_query(url $url, array &$parameters): void {
}

public static function on_post_query(url $url, array &$items): void {
}

public static function on_pre_render(url $url, template $template): void {
}

public static function on_post_render(url $url, string &$html): void {
}

public static function on_render_row(url $url, array &$row, int $row_index): void {
}
}
