<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Core\GameConstants;
use App\Services\UserSettingsService;
use PHPUnit\Framework\TestCase;

final class UserSettingsServiceTest extends TestCase
{
    protected function setUp(): void
    {
        $GLOBALS['ListCensure'] = GameConstants::censoredWords();
    }

    protected function tearDown(): void
    {
        unset($GLOBALS['ListCensure']);
    }

    public function testFlagReadsCheckboxes(): void
    {
        self::assertSame('1', UserSettingsService::flag(array('design' => 'on'), 'design'));
        self::assertSame('0', UserSettingsService::flag(array(), 'design'));
        self::assertSame('0', UserSettingsService::flag(array('design' => 'off'), 'design'));
        self::assertSame('0', UserSettingsService::flag(array('design' => 'on'), 'noipcheck'));
    }

    public function testNumberKeepsPositiveIntegers(): void
    {
        self::assertSame('12', UserSettingsService::number(array('spy_count' => '12'), 'spy_count'));
        self::assertSame('7', UserSettingsService::number(array('spy_count' => 7.9), 'spy_count'));
        self::assertSame('1', UserSettingsService::number(array('spy_count' => '-3'), 'spy_count'));
        self::assertSame('1', UserSettingsService::number(array('spy_count' => 'abc'), 'spy_count'));
        self::assertSame('5', UserSettingsService::number(array(), 'spy_count', '5'));
    }

    public function testIdentityFallsBackOnTheAccount(): void
    {
        $user = array('username' => 'Ancien', 'email' => 'ancien@example.com');

        self::assertSame('Ancien', UserSettingsService::username(array(), $user));
        self::assertSame('Ancien', UserSettingsService::username(array('db_character' => '  '), $user));
        // CheckInputStrings (legacy) censure les chevrons et les mots interdits.
        self::assertSame('Nouveau***nom', UserSettingsService::username(array('db_character' => 'Nouveau<script>nom'), $user));
        self::assertSame('ancien@example.com', UserSettingsService::email(array(), $user));
    }

    public function testPasswordChangeDetection(): void
    {
        self::assertFalse(UserSettingsService::wantsPasswordChange(array()));
        self::assertFalse(UserSettingsService::wantsPasswordChange(array('db_password' => '')));
        self::assertTrue(UserSettingsService::wantsPasswordChange(array('db_password' => 'secret')));
    }

    public function testPasswordProblems(): void
    {
        $hash = md5('secret');
        $base = array('db_password' => 'secret', 'newpass1' => 'nouveau', 'newpass2' => 'nouveau');

        self::assertNull(UserSettingsService::passwordProblem($base, $hash));
        self::assertSame('wrong_password', UserSettingsService::passwordProblem(array('db_password' => 'faux'), $hash));
        self::assertSame('password_empty', UserSettingsService::passwordProblem(array('db_password' => 'secret'), $hash));
        self::assertSame('password_mismatch', UserSettingsService::passwordProblem(
            array('db_password' => 'secret', 'newpass1' => 'a', 'newpass2' => 'b'),
            $hash
        ));
        self::assertSame(md5('nouveau'), UserSettingsService::newPasswordHash($base));
    }

    public function testSettingsKeepTheFormKeys(): void
    {
        $user = array(
            'username' => 'Joueur',
            'email' => 'joueur@example.com',
            'dpath' => 'public/xnova/',
            'planet_sort' => 1,
            'planet_sort_order' => 0,
        );
        $settings = UserSettingsService::settings(array('design' => 'on', 'settings_esp' => 'on'), $user);

        self::assertSame('joueur@example.com', $settings['email']);
        self::assertSame('public/xnova/', $settings['dpath']);
        self::assertSame('1', $settings['design']);
        self::assertSame('0', $settings['noipcheck']);
        self::assertSame('1', $settings['settings_esp']);
        self::assertSame(1, $settings['planet_sort']);
        self::assertSame('0', $settings['vacation_mode']);
        self::assertSame('1', UserSettingsService::settings(array(), $user, array('vacation_mode' => '1'))['vacation_mode']);
        self::assertSame(172800, UserSettingsService::VACATION_SECONDS);
    }
}
