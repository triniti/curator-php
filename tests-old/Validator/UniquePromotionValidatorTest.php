<?php
declare(strict_types=1);

namespace Triniti\Tests\Curator\Validator;

use Acme\Schemas\Curator\Command\CreatePromotionV1;
use Acme\Schemas\Curator\Command\UpdatePromotionV1;
use Acme\Schemas\Curator\Event\PromotionCreatedV1;
use Acme\Schemas\Curator\Node\PromotionV1;
use Acme\Schemas\Curator\Request\GetPromotionRequestV1;
use Gdbots\Ncr\Exception\NodeAlreadyExists;
use Gdbots\Ncr\Validator\UniqueNodeValidator;
use Gdbots\Pbj\Exception\AssertionFailed;
use Gdbots\Pbjx\Event\PbjxEvent;
use Gdbots\Schemas\Pbjx\StreamId;
use Triniti\Curator\GetPromotionRequestHandler;
use Triniti\Tests\Curator\AbstractPbjxTest;

final class UniquePromotionValidatorTest extends AbstractPbjxTest
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
            GetPromotionRequestV1::schema()->getCurie(),
            new GetPromotionRequestHandler($this->ncr)
        );
    }

    public function testValidateCreatePromotionThatDoesNotExist(): void
    {
        $node = PromotionV1::create();
        $command = CreatePromotionV1::create();
        $command->set('node', $node);
        $pbjxEvent = new PbjxEvent($command);
        $validator = new UniqueNodeValidator();
        $validator->validateCreateNode($pbjxEvent);
        // if it gets here it's a pass
        $this->assertTrue(true);
    }

    public function testValidateCreatePromotionThatDoesExistById(): void
    {
        $this->expectException(NodeAlreadyExists::class);
        $node = PromotionV1::create();
        $event = PromotionCreatedV1::create()->set('node', $node);
        $this->eventStore->putEvents(
            StreamId::fromString("promotion.history:{$node->get('_id')}"),
            [$event]
        );
        $command = CreatePromotionV1::create()->set('node', $node);
        $validator = new UniqueNodeValidator();
        $pbjxEvent = new PbjxEvent($command);
        $validator->validateCreateNode($pbjxEvent);
    }

    public function testValidateUpdatePromotionFailsWithoutANewNode(): void
    {
        $this->expectException(AssertionFailed::class);
        $command = UpdatePromotionV1::create()->set('old_node', PromotionV1::create());
        $pbjxEvent = new PbjxEvent($command);
        $validator = new UniqueNodeValidator();
        $validator->validateCreateNode($pbjxEvent);
    }
}
