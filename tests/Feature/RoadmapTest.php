<?php

namespace Tests\Feature;

use Tests\TestCase;

class RoadmapTest extends TestCase
{
    public function test_public_roadmap_and_votes_are_disabled(): void
    {
        $this->get('/roadmap')->assertNotFound();
        $this->post('/roadmap', ['title' => 'Request'])->assertNotFound();
        $this->post('/roadmap/1/vote')->assertNotFound();
    }
}
