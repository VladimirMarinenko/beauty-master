<?php

namespace Tests\Unit;

use App\Models\Client;
use App\Models\User;
use PHPUnit\Framework\TestCase;

class PhoneHelpersTest extends TestCase
{
    // ===== normalizePhone =====

    public function test_normalize_phone_converts_8_to_7(): void
    {
        $this->assertSame('79613322412', Client::normalizePhone('89613322412'));
        $this->assertSame('79613322412', User::normalizePhone('89613322412'));
    }

    public function test_normalize_phone_strips_all_non_digits(): void
    {
        $this->assertSame('79613322412', Client::normalizePhone('+7 (961) 332-24-12'));
        $this->assertSame('79613322412', Client::normalizePhone('+7–961–332–24–12'));
        $this->assertSame('79613322412', Client::normalizePhone('  +7 961 332 24 12 '));
    }

    public function test_normalize_phone_returns_null_for_placeholder(): void
    {
        $this->assertNull(Client::normalizePhone('+7 (___) ___-__-__'));
        $this->assertNull(User::normalizePhone('+7 (___) ___-__-__'));
    }

    public function test_normalize_phone_returns_null_for_empty(): void
    {
        $this->assertNull(Client::normalizePhone(null));
        $this->assertNull(Client::normalizePhone(''));
        $this->assertNull(Client::normalizePhone('   '));
        $this->assertNull(Client::normalizePhone('abc'));
    }

    public function test_normalize_phone_keeps_short_numbers_above_10_digits(): void
    {
        $this->assertSame('9613322412', Client::normalizePhone('9613322412'));
        $this->assertSame('79613322412', Client::normalizePhone('79613322412'));
    }

    // ===== looksLikePhone =====

    public function test_looks_like_phone_returns_true_for_phone(): void
    {
        $this->assertTrue(Client::looksLikePhone('+79613322412'));
        $this->assertTrue(Client::looksLikePhone('89613322412'));
        $this->assertTrue(Client::looksLikePhone('+7 (961) 332-24-12'));
        $this->assertTrue(Client::looksLikePhone('961'));
    }

    public function test_looks_like_phone_returns_false_for_text(): void
    {
        $this->assertFalse(Client::looksLikePhone('Иван'));
        $this->assertFalse(Client::looksLikePhone('Марковка Вовка'));
        $this->assertFalse(Client::looksLikePhone('@instagram'));
        $this->assertFalse(Client::looksLikePhone('test@test.com'));
        $this->assertFalse(Client::looksLikePhone(''));
    }

    public function test_looks_like_phone_returns_false_for_short_digits(): void
    {
        $this->assertFalse(Client::looksLikePhone('12'));
        $this->assertFalse(Client::looksLikePhone('+7'));
    }

    // ===== phoneSearchVariants =====

    public function test_phone_search_variants_8_and_7(): void
    {
        $this->assertSame(['8961', '7961'], Client::phoneSearchVariants('8961'));
        $this->assertSame(['7961', '8961'], Client::phoneSearchVariants('7961'));
    }

    public function test_phone_search_variants_short(): void
    {
        // 1 цифра — не разворачиваем
        $this->assertSame(['8'], Client::phoneSearchVariants('8'));
        $this->assertSame(['7'], Client::phoneSearchVariants('7'));

        // Не начинается с 8/7 — не разворачиваем (961 — это фрагмент, а не начало)
        $this->assertSame(['961'], Client::phoneSearchVariants('961'));
        $this->assertSame(['9613322'], Client::phoneSearchVariants('9613322'));

        // Начинается с 8/7 — разворачиваем
        $this->assertSame(['861', '761'], Client::phoneSearchVariants('861'));
        $this->assertSame(['761', '861'], Client::phoneSearchVariants('761'));
    }

    public function test_phone_search_variants_empty(): void
    {
        $this->assertSame([], Client::phoneSearchVariants(''));
    }
}
