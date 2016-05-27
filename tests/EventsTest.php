<?php

class EventsTest extends TestCase {

    public function testRawListing()
    {
        $this->visit('/events')
             ->see('count');
    }

}
