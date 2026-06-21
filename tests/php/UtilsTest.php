<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Cospend;

use PHPUnit\Framework\TestCase;

class UtilsTest extends TestCase {

	/**
	 * @return void
	 */
	public function testHexToRgb(): void {
		$this->assertEquals(
			[
				'r' => 255,
				'g' => 0,
				'b' => 0,
			],
			Utils::hexToRgb('#ff0000')
		);
		$this->assertEquals(
			[
				'r' => 213,
				'g' => 148,
				'b' => 14,
			],
			Utils::hexToRgb('#d5940e')
		);
	}

	/**
	 * @return void
	 */
	public function testSlugify(): void {
		$this->assertEquals('test-string', Utils::slugify('  test string '));
		$this->assertEquals('t-est-string', Utils::slugify('t Est STRING'));
		$this->assertEquals('test_string', Utils::slugify('test&STRING'));
		$this->assertEquals('oeaeue', Utils::slugify('Öäü'));
	}
}
