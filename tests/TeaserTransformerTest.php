<?php

namespace Gdbots\Tests\Common;

use Acme\Schemas\Canvas\Block\TextBlockV1;
use Acme\Schemas\Curator\Node\ArticleTeaserV1;
use Acme\Schemas\News\Node\ArticleV1;
use Gdbots\Schemas\Ncr\NodeRef;
use PHPUnit\Framework\TestCase;
use Triniti\Curator\TeaserTransformer;

class TeaserTransformerTest extends TestCase
{
    private $simpleFieldNames = [
        'ads_enabled',
        'is_unlisted',
        'meta_description',
        'seo_title',
        'swipe',
        'theme',
        'title',
    ];

    private $refFieldNames = [
        'channel_ref',
        'image_ref',
        'seo_image_ref',
        'sponsor_ref',
    ];

    public function testTransform() {
        $teaser = ArticleTeaserV1::create()
            ->set('meta_description', 'whatever')
            ->set('seo_title', 'whatever')
            ->set('swipe', 'whatever')
            ->set('theme', 'whatever')
            ->set('title', 'whatever')
            ->set('ads_enabled', false)
            ->set('is_unlisted', false)
            ->set('order_date', \DateTime::createFromFormat('j-M-Y', '15-Feb-2009'));

        $teaser->set('description', 'whatever');

        $target = ArticleV1::create()
            ->set('meta_description', 'equally-whatever')
            ->set('seo_title', 'equally-whatever')
            ->set('swipe', 'equally-whatever')
            ->set('theme', 'equally-whatever')
            ->set('title', 'equally-whatever')
            ->set('ads_enabled', true)
            ->set('is_unlisted', true)
            ->set('order_date', \DateTime::createFromFormat('j-M-Y', '15-Feb-2010'));

        $target->addToList('blocks', [TextBlockV1::create()->set('text', 'equally-whatever')]);

        foreach ($this->simpleFieldNames as $fieldName) {
            $this->assertNotSame($teaser->get($fieldName), $target->get($fieldName));
        }

        foreach($this->refFieldNames as $fieldName) {
            $teaser->set($fieldName, NodeRef::fromString('not:a:real:noderef'));
            $target->set($fieldName, NodeRef::fromString('alsonot:a:real:noderef'));
            $this->assertNotSame($teaser->get($fieldName)->toString(), $target->get($fieldName)->toString());
        }

        $teaser = TeaserTransformer::transform($target, $teaser);

        foreach ($this->simpleFieldNames as $fieldName) {
            $this->assertSame($teaser->get($fieldName), $target->get($fieldName));
        }

        foreach($this->refFieldNames as $fieldName) {
            $this->assertSame($teaser->get($fieldName)->toString(), $target->get($fieldName)->toString());
        }

        $this->assertSame($teaser->get('description'), $target->get('blocks')[0]->get('text'));
    }
}
