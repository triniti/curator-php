<?php
declare(strict_types=1);

namespace Triniti\Tests\Curator;

use Acme\Schemas\Curator\Node\ArticleTeaserV1;
use Gdbots\Pbj\SchemaCurie;
use Gdbots\Schemas\Pbjx\StreamId;
use Triniti\Curator\RemoveTeaserSlottingHandler;
use Triniti\Schemas\Curator\Command\RemoveTeaserSlottingV1;
use Triniti\Schemas\Curator\Event\TeaserSlottingRemovedV1;

final class RemoveTeaserSlottingHandlerTest extends AbstractPbjxTest
{
    public function testHandleCommand(): void
    {
        $teaser = ArticleTeaserV1::create()->addToMap('slotting', 'home', 1);
        $ncrSearch = new MockNcrSearch();
        $ncrSearch->indexNodes([$teaser]);
        $this->locator->registerRequestHandler(
            SchemaCurie::fromString('triniti:curator:request:search-teasers-request'),
            new MockSearchNodesRequestHandler($ncrSearch)
        );
        $command = RemoveTeaserSlottingV1::create()->addToMap('slotting', 'home', 1);
        $handler = new RemoveTeaserSlottingHandler();
        $handler->handleCommand($command, $this->pbjx);

        foreach ($this->eventStore->pipeAllEvents() as [$event, $streamId]) {
            $this->assertInstanceOf(TeaserSlottingRemovedV1::class, $event);
            $this->assertTrue($teaser->generateNodeRef()->equals($event->get('node_ref')));
            $this->assertSame('home', $event->get('slotting_keys')[0]);
            $this->assertTrue(StreamId::fromString("acme:article-teaser:{$teaser->generateNodeRef()->getId()}")->equals($streamId));
        }
    }

    public function testHandleCommandNoSlotting(): void
    {
        $teaser = ArticleTeaserV1::create()->addToMap('slotting', 'home', 1);
        $ncrSearch = new MockNcrSearch();
        $ncrSearch->indexNodes([$teaser]);
        $this->locator->registerRequestHandler(
            SchemaCurie::fromString('triniti:curator:request:search-teasers-request'),
            new MockSearchNodesRequestHandler($ncrSearch)
        );
        $command = RemoveTeaserSlottingV1::create();
        $handler = new RemoveTeaserSlottingHandler();
        $handler->handleCommand($command, $this->pbjx);

        $eventCount = 0;
        foreach ($this->eventStore->pipeAllEvents() as [$event, $streamId]) {
            $eventCount++;
        }
        $this->assertSame(0, $eventCount);
    }

    public function testHandleCommandNoSlottingConflicts(): void
    {
        $teaser = ArticleTeaserV1::create()->addToMap('slotting', 'home', 1);
        $ncrSearch = new MockNcrSearch();
        $ncrSearch->indexNodes([$teaser]);
        $this->locator->registerRequestHandler(
            SchemaCurie::fromString('triniti:curator:request:search-teasers-request'),
            new MockSearchNodesRequestHandler($ncrSearch),
            );
        $command = RemoveTeaserSlottingV1::create()->addToMap('slotting', 'home', 2);
        $handler = new RemoveTeaserSlottingHandler();
        $handler->handleCommand($command, $this->pbjx);

        $eventCount = 0;
        foreach ($this->eventStore->pipeAllEvents() as [$event, $streamId]) {
            $eventCount++;
        }
        $this->assertSame(0, $eventCount);
    }
}
