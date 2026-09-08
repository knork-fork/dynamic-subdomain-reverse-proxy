<?php

declare(strict_types=1);

namespace App\Tests\Functional\Controller;

use App\Tests\Common\FunctionalTestCase;

/**
 * @internal
 */
final class StatusControllerTest extends FunctionalTestCase
{
    public function testStatusReturnsOk(): void
    {
        $this->request('GET', '/status');

        $this->assertJsonResponse(['status' => 'ok']);
    }
}
