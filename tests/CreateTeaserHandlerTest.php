<?php
declare(strict_types=1);

namespace Triniti\Tests\Curator;

use Acme\Schemas\Curator\Command\CreateTeaserV1;
use Acme\Schemas\Curator\Event\TeaserCreatedV1;
use Acme\Schemas\Curator\Node\ArticleTeaserV1;
use Acme\Schemas\Curator\Node\GalleryTeaserV1;
use Acme\Schemas\Curator\Node\TimelineTeaserV1;
use Acme\Schemas\Curator\Node\VideoTeaserV1;
use Acme\Schemas\Curator\Node\YoutubeVideoTeaserV1;
use Gdbots\Schemas\Pbjx\Mixin\Event\Event;
use Gdbots\Schemas\Pbjx\StreamId;
use Triniti\Curator\CreateTeaserHandler;

final class CreateTeaserHandlerTest extends AbstractPbjxTest
{

    public function testHandleCommandForArticleTeaser(): void
    {
        $title = 'test-title';

        $node = ArticleTeaserV1::create()
            ->set('title', $title);

        $command = CreateTeaserV1::create()
            ->set('node', $node);

        $expectedEvent = TeaserCreatedV1::create();
        $expectedId = $node->get('_id');

        $handler = new CreateTeaserHandler();
        $handler->handleCommand($command, $this->pbjx);

        $this->eventStore->pipeAllEvents(function (Event $event, StreamId $streamId)

        use ($expectedEvent, $expectedId, $title) {
            $actualNode = $event->get('node');
            $this->assertSame($event::schema(), $expectedEvent::schema());
            $this->assertSame($title, $actualNode->get('title'));
            $this->assertSame(StreamId::fromString("article-teaser.history:{$expectedId}")->toString(), $streamId->toString());
            $this->assertSame($event->generateMessageRef()->toString(), (string)$actualNode->get('last_event_ref'));
        });
    }

    public function testHandleCommandForGalleryTeaser(): void
    {
        $title = 'test-title';

        $node = GalleryTeaserV1::create()
            ->set('title', $title);

        $command = CreateTeaserV1::create()
            ->set('node', $node);

        $expectedEvent = TeaserCreatedV1::create();
        $expectedId = $node->get('_id');

        $handler = new CreateTeaserHandler();
        $handler->handleCommand($command, $this->pbjx);

        $this->eventStore->pipeAllEvents(function (Event $event, StreamId $streamId)

        use ($expectedEvent, $expectedId, $title) {
            $actualNode = $event->get('node');
            $this->assertSame($event::schema(), $expectedEvent::schema());
            $this->assertSame($title, $actualNode->get('title'));
            $this->assertSame(StreamId::fromString("gallery-teaser.history:{$expectedId}")->toString(), $streamId->toString());
            $this->assertSame($event->generateMessageRef()->toString(), (string)$actualNode->get('last_event_ref'));
        });
    }

    public function testHandleCommandForTimelineTeaser(): void
    {
        $title = 'test-title';

        $node = TimelineTeaserV1::create()
            ->set('title', $title);

        $command = CreateTeaserV1::create()
            ->set('node', $node);

        $expectedEvent = TeaserCreatedV1::create();
        $expectedId = $node->get('_id');

        $handler = new CreateTeaserHandler();
        $handler->handleCommand($command, $this->pbjx);

        $this->eventStore->pipeAllEvents(function (Event $event, StreamId $streamId)

        use ($expectedEvent, $expectedId, $title) {
            $actualNode = $event->get('node');
            $this->assertSame($event::schema(), $expectedEvent::schema());
            $this->assertSame($title, $actualNode->get('title'));
            $this->assertSame(StreamId::fromString("timeline-teaser.history:{$expectedId}")->toString(), $streamId->toString());
            $this->assertSame($event->generateMessageRef()->toString(), (string)$actualNode->get('last_event_ref'));
        });
    }

    public function testHandleCommandForVideoTeaser(): void
    {
        $title = 'test-title';

        $node = VideoTeaserV1::create()
            ->set('title', $title);

        $command = CreateTeaserV1::create()
            ->set('node', $node);

        $expectedEvent = TeaserCreatedV1::create();
        $expectedId = $node->get('_id');

        $handler = new CreateTeaserHandler();
        $handler->handleCommand($command, $this->pbjx);

        $this->eventStore->pipeAllEvents(function (Event $event, StreamId $streamId)

        use ($expectedEvent, $expectedId, $title) {
            $actualNode = $event->get('node');
            $this->assertSame($event::schema(), $expectedEvent::schema());
            $this->assertSame($title, $actualNode->get('title'));
            $this->assertSame(StreamId::fromString("video-teaser.history:{$expectedId}")->toString(), $streamId->toString());
            $this->assertSame($event->generateMessageRef()->toString(), (string)$actualNode->get('last_event_ref'));
        });
    }

    public function testHandleCommandForYoutubeVideoTeaser(): void
    {
        $title = 'test-title';

        $node = YoutubeVideoTeaserV1::create()
            ->set('title', $title);

        $command = CreateTeaserV1::create()
            ->set('node', $node);

        $expectedEvent = TeaserCreatedV1::create();
        $expectedId = $node->get('_id');

        $handler = new CreateTeaserHandler();
        $handler->handleCommand($command, $this->pbjx);

        $this->eventStore->pipeAllEvents(function (Event $event, StreamId $streamId)

        use ($expectedEvent, $expectedId, $title) {
            $actualNode = $event->get('node');
            $this->assertSame($event::schema(), $expectedEvent::schema());
            $this->assertSame($title, $actualNode->get('title'));
            $this->assertSame(StreamId::fromString("youtube-video-teaser.history:{$expectedId}")->toString(), $streamId->toString());
            $this->assertSame($event->generateMessageRef()->toString(), (string)$actualNode->get('last_event_ref'));
        });
    }
}