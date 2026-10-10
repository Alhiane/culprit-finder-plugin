<?php
/**
 * Add-on setup section: which `culprit_finder_setup_fields` output gets a numbered row (ADR-0026).
 *
 * @package CulpritFinder
 */

namespace CulpritFinder\Tests;

use CulpritFinder\Admin\TroubleshootView;
use PHPUnit\Framework\TestCase;

final class SetupSectionTest extends TestCase {

	public function test_text_and_visible_controls_get_a_row(): void {
		$visible = array(
			'<fieldset class="cf-field"><legend>Acme</legend><input type="text" name="culprit_finder_addon[acme][x]"></fieldset>',
			'<p>Just text</p>',
			'plain text',
			'<input type="checkbox" name="culprit_finder_addon[acme][x]">',
			'<input name="culprit_finder_addon[acme][x]">',
			'<select name="culprit_finder_addon[acme][x]"><option></option></select>',
			'<textarea name="culprit_finder_addon[acme][x]"></textarea>',
			'<button type="button"></button>',
			'<svg width="10" height="10"></svg>',
			'<p>&copy;</p>',
		);
		foreach ( $visible as $html ) {
			$this->assertTrue( TroubleshootView::has_visible_content( $html ), $html );
		}
	}

	public function test_nothing_hidden_fields_comments_scripts_and_whitespace_get_no_row(): void {
		$invisible = array(
			'',
			"  \n\t ",
			'<!-- note -->',
			'<input type="hidden" name="culprit_finder_addon[acme][x]" value="1">',
			"<INPUT TYPE='HIDDEN' name='a'>",
			'<input type=hidden name=a>',
			'<script>var a = "<b>text</b>";</script>',
			'<style>.a::before { content: "x"; }</style>',
			'<template><p>later</p></template>',
			'<div class="acme"></div>',
			"<!-- a -->\n<input type=\"hidden\" name=\"a\" value=\"b\">\n<script>window.a = 1;</script>",
			'<p>&nbsp;</p>',
		);
		foreach ( $invisible as $html ) {
			$this->assertFalse( TroubleshootView::has_visible_content( $html ), $html );
		}
	}
}
