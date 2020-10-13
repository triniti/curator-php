<?php
declare(strict_types=1);

namespace Triniti\Tests\Curator\Validator;

use Acme\Schemas\Curator\Command\CreateTeaserV1;
use Acme\Schemas\Curator\Command\UpdateTeaserV1;
use Acme\Schemas\Curator\Event\TeaserCreatedV1;
use Acme\Schemas\Curator\Node\ArticleTeaserV1;
use Acme\Schemas\Curator\Request\GetTeaserRequestV1;
use Gdbots\Ncr\Exception\NodeAlreadyExists;
use Gdbots\Ncr\Validator\UniqueNodeValidator;
use Gdbots\Pbj\Exception\AssertionFailed;
use Gdbots\Pbjx\Event\PbjxEvent;
use Gdbots\Schemas\Pbjx\StreamId;
use Triniti\Curator\GetTeaserRequestHandler;
use Triniti\Tests\Curator\AbstractPbjxTest;

final class UniqueTeaserValidatorTest extends AbstractPbjxTest
{
    /**
     * Prepare the test.
     */
    public function setup(): void
    {
        parent::setup();
        // prepare request handlers that this test case requires
        PbjxEvent::setPbjx($this->pbjx);
        $this->locator->registerRequestHandler(
            GetTeaserRequestV1::schema()->getCurie(),
            new GetTeaserRequestHandler($this->ncr)
        );
    }

    public function testValidateCreateTeaserThatDoesNotExist(): void
    {
        $node = ArticleTeaserV1::create();
        $command = CreateTeaserV1::create();
        $command->set('node', $node);
        $pbjxEvent = new PbjxEvent($command);
        $validator = new UniqueNodeValidator();
        $validator->validateCreateNode($pbjxEvent);
        // if it gets here it's a pass
        $this->assertTrue(true);
    }

    public function testValidateCreateTeaserThatDoesExistById(): void
    {
        $this->expectException(NodeAlreadyExists::class);
        $node = ArticleTeaserV1::create();
        $event = TeaserCreatedV1::create()->set('node', $node);
        $this->eventStore->putEvents(
            StreamId::fromString("article-teaser.history:{$node->get('_id')}"),
            [$event]
        );
        $command = CreateTeaserV1::create()->set('node', $node);
        $validator = new UniqueNodeValidator();
        $pbjxEvent = new PbjxEvent($command);
        $validator->validateCreateNode($pbjxEvent);
    }

    public function testValidateUpdateTeaserFailsWithoutANewNode(): void
    {
        $this->expectException(AssertionFailed::class);
        $command = UpdateTeaserV1::create()->set('old_node', CreateTeaserV1::create());
        $pbjxEvent = new PbjxEvent($command);
        $validator = new UniqueNodeValidator();
        $validator->validateCreateNode($pbjxEvent);
    }
}
