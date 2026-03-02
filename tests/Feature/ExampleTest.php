<?php

namespace Tests\Feature;

use Tests\TestCase;

class ExampleTest extends TestCase
{
    public function test_the_application_redirects_to_shop_home(): void
    {
        $response = $this->get('/');

        $response->assertRedirect('/shop/index.html');
    }
}
