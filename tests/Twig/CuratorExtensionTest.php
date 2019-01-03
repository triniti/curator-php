<?php
declare(strict_types=1);

namespace Triniti\Tests\Curator\Twig;

use Acme\Schemas\Curator\Node\CodeWidgetV1;
use Acme\Schemas\Curator\Request\RenderWidgetRequestV1;
use Gdbots\Schemas\Ncr\NodeRef;
use Symfony\Component\HttpFoundation\RequestStack;
use Triniti\Curator\RenderWidgetRequestHandler;
use Triniti\Curator\Twig\CuratorExtension;
use Triniti\Schemas\Common\RenderContextV1;
use Triniti\Tests\Curator\AbstractPbjxTest;

final class CuratorExtensionTest extends AbstractPbjxTest
{
    /** @var \Twig_Environment */
    private $twig;

    public function setup()
    {
        parent::setup();
        $loader = new \Twig_Loader_Filesystem(__DIR__ . '/../Fixtures/templates/');
        $loader->addPath(realpath(__DIR__ . '/../Fixtures/templates/'), 'curator_widgets');
        $this->twig = new \Twig_Environment($loader, ['debug' => true]);
        $this->twig->addExtension(new CuratorExtension($this->pbjx, new RequestStack()));

        $this->locator->registerRequestHandler(
            RenderWidgetRequestV1::schema()->getCurie(),
            new RenderWidgetRequestHandler($this->ncr, $this->twig)
        );
    }

    public function testWithPlatform(): void
    {
        $widget = CodeWidgetV1::create()->set('code', 'test');
        $this->ncr->putNode($widget);

        $context = RenderContextV1::create()
            ->set('platform', 'web')
            ->set('device_view', 'smartphone');

        $actual = $this->twig->render('curator_render_widget.twig', [
            'pbj'     => NodeRef::fromNode($widget),
            'context' => $context,
        ]);
        $expected = "{$widget}{$context}";

        $this->assertSame(trim($expected), trim($actual));
    }
}
