<?php

class EventsTest extends BrowserKitTestCase
{
    public function testRawListing()
    {
        $this->visit('/events')
             ->see('count');
    }
}
