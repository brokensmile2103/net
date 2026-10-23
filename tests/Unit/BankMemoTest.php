<?php

namespace Tests\Unit;

use App\Models\Studio;
use PHPUnit\Framework\TestCase;

class BankMemoTest extends TestCase
{
    public function test_default_template_gives_the_original_memo(): void
    {
        $this->assertSame('NET N0001', Studio::renderMemo(Studio::DEFAULT_BANK_MEMO, ['code' => 'N0001']));
    }

    public function test_empty_template_gives_empty_memo(): void
    {
        $this->assertSame('', Studio::renderMemo('', ['code' => 'N0001']));
    }

    public function test_accents_and_unknown_variables_are_stripped(): void
    {
        $this->assertSame('Thanh toan N0001', Studio::renderMemo('Thanh toán {code} {abc}', ['code' => 'N0001']));
        $this->assertSame('Thanh toan {code}', Studio::cleanMemoTemplate('Thanh toán {code}!'));
    }
}
