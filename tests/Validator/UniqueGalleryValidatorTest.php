<?php
declare(strict_types=1);

namespace Triniti\Tests\Curator\Validator;

use Acme\Schemas\Curator\Command\CreateGalleryV1;
use Acme\Schemas\Curator\Command\RenameGalleryV1;
use Acme\Schemas\Curator\Command\UpdateGalleryV1;
use Acme\Schemas\Curator\Event\GalleryCreatedV1;
use Acme\Schemas\Curator\Node\GalleryV1;
use Acme\Schemas\Curator\Request\GetGalleryRequestV1;
use Gdbots\Ncr\Exception\NodeAlreadyExists;
use Gdbots\Ncr\Validator\UniqueNodeValidator;
use Gdbots\Pbj\Exception\AssertionFailed;
use Gdbots\Pbjx\Event\PbjxEvent;
use Gdbots\Schemas\Ncr\NodeRef;
use Gdbots\Schemas\Pbjx\StreamId;
use Triniti\Curator\GetGalleryRequestHandler;
use Triniti\Tests\Curator\AbstractPbjxTest;

final class UniqueGalleryValidatorTest extends AbstractPbjxTest
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
            GetGalleryRequestV1::schema()->getCurie(),
            new GetGalleryRequestHandler($this->ncr)
        );
    }

    public function testValidateCreateGalleryThatDoesNotExist(): void
    {
        $node = GalleryV1::create();
        $command = CreateGalleryV1::create();
        $command->set('node', $node);
        $pbjxEvent = new PbjxEvent($command);
        $validator = new UniqueNodeValidator();
        $validator->validateCreateNode($pbjxEvent);
        // if it gets here it's a pass
        $this->assertTrue(true);
    }

    public function testValidateCreateGalleryThatDoesExistBySlug(): void
    {
        $this->expectException(NodeAlreadyExists::class);
        $existingNode = GalleryV1::create()->set('slug', 'existing-gallery-slug');
        $newNode = GalleryV1::create()->set('slug', 'existing-gallery-slug');
        $this->ncr->putNode($existingNode);
        $command = CreateGalleryV1::create();
        $command->set('node', $newNode);
        $validator = new UniqueNodeValidator();
        $pbjxEvent = new PbjxEvent($command);
        $validator->validateCreateNode($pbjxEvent);
    }

    public function testValidateCreateGalleryThatDoesExistById(): void
    {
        $this->expectException(NodeAlreadyExists::class);
        $node = GalleryV1::create();
        $event = GalleryCreatedV1::create()->set('node', $node);
        $this->eventStore->putEvents(
            StreamId::fromString("gallery.history:{$node->get('_id')}"),
            [$event]
        );
        $command = CreateGalleryV1::create()->set('node', $node);
        $validator = new UniqueNodeValidator();
        $pbjxEvent = new PbjxEvent($command);
        $validator->validateCreateNode($pbjxEvent);
    }

    public function testValidateUpdateGalleryFailsWithoutANewNode(): void
    {
        $this->expectException(AssertionFailed::class);
        $command = UpdateGalleryV1::create()->set('old_node', GalleryV1::create());
        $pbjxEvent = new PbjxEvent($command);
        $validator = new UniqueNodeValidator();
        $validator->validateCreateNode($pbjxEvent);
    }

    public function testValidateUpdateGallerySlugIsCopied(): void
    {
        $oldEntity = GalleryV1::create()->set('slug', 'first-gallery');
        $newEntity = GalleryV1::create()->set('slug', 'first-updated-gallery');
        $command = UpdateGalleryV1::create()
            ->set('old_node', $oldEntity)
            ->set('new_node', $newEntity);
        $pbjxEvent = new PbjxEvent($command);
        $validator = new UniqueNodeValidator();
        $validator->validateUpdateNode($pbjxEvent);
        $this->assertSame('first-gallery', $command->get('new_node')->get('slug'));
    }

    public function testValidateRenameGallery(): void
    {
        $entity = GalleryV1::create();
        $command = RenameGalleryV1::create()
            ->set('node_ref', NodeRef::fromNode($entity))
            ->set('new_slug', 'new-slug-for-gallery');
        $pbjxEvent = new PbjxEvent($command);
        $validator = new UniqueNodeValidator();
        $validator->validateRenameNode($pbjxEvent);
        // if it gets here then it's a pass
        $this->assertTrue(true);
    }

    public function testValidateRenameGalleryWithoutNodeRef(): void
    {
        $this->expectException(AssertionFailed::class);
        $command = RenameGalleryV1::create();
        $pbjxEvent = new PbjxEvent($command);
        $validator = new UniqueNodeValidator();
        $validator->validateRenameNode($pbjxEvent);
    }

    public function testValidateRenameGalleryWithoutNewSlug(): void
    {
        $this->expectException(AssertionFailed::class);
        $entity = GalleryV1::create();
        $command = RenameGalleryV1::create()
            ->set('node_ref', NodeRef::fromNode($entity));
        $pbjxEvent = new PbjxEvent($command);
        $validator = new UniqueNodeValidator();
        $validator->validateRenameNode($pbjxEvent);
        // if it gets here then it's a pass
        $this->assertTrue(true);
    }
}
