<?php
declare(strict_types=1);

namespace Triniti\Curator;

use Gdbots\Ncr\AbstractSearchNodesRequestHandler;
use Gdbots\Ncr\NcrSearch;
use Gdbots\QueryParser\Enum\BoolOperator;
use Gdbots\QueryParser\Enum\ComparisonOperator;
use Gdbots\QueryParser\Node\Field;
use Gdbots\QueryParser\Node\Numbr;
use Gdbots\QueryParser\Node\Word;
use Gdbots\QueryParser\ParsedQuery;
use Gdbots\Schemas\Ncr\Mixin\SearchNodesRequest\SearchNodesRequest;
use Triniti\Schemas\Curator\Mixin\SearchPromotionsRequest\SearchPromotionsRequestV1Mixin;

class SearchPromotionsRequestHandler extends AbstractSearchNodesRequestHandler
{
    /** @var \DateTimeZone */
    protected $timeZone;

    /**
     * @param NcrSearch $ncrSearch
     * @param string    $timeZone
     */
    public function __construct(NcrSearch $ncrSearch, ?string $timeZone = null)
    {
        parent::__construct($ncrSearch);
        $this->timeZone = new \DateTimeZone($timeZone ?: 'UTC');
    }

    /**
     * {@inheritdoc}
     */
    protected function beforeSearchNodes(SearchNodesRequest $request, ParsedQuery $parsedQuery): void
    {
        parent::beforeSearchNodes($request, $parsedQuery);
        $required = BoolOperator::REQUIRED();

        if ($request->has('slot')) {
            $parsedQuery->addNode(
                new Field(
                    'slot',
                    new Word((string)$request->get('slot'), $required),
                    $required
                )
            );
        }

        if ($request->has('render_at')) {
            /** @var \DateTime|\DateTimeImmutable $renderAt */
            $renderAt = $request->get('render_at');
            $renderAt = $renderAt->setTimezone($this->timeZone);

            $sod = (float)((int)strtotime("{$renderAt->format('H:i:s')}UTC", 0));
            $dow = strtolower($renderAt->format('D'));

            // This expects a NodeMapper for elasticsearch (or whatever provider the NcrSearch is
            // configured to use) storing the d__{$dow}_start_at and d__{$dow}_end_at as seconds
            // since midnight rather than the human friendly HH:MM:SS values in {$dow}_start_at
            // and {$dow}_end_at. the "d__" prefix is used to signify a derived field that is
            // created at index time.  it is not actually a part of the schema on the promotion.
            $parsedQuery
                ->addNode(
                    new Field(
                        "d__{$dow}_start_at",
                        new Numbr($sod, ComparisonOperator::LTE()),
                        $required
                    )
                )->addNode(
                    new Field(
                        "d__{$dow}_end_at",
                        new Numbr($sod, ComparisonOperator::GTE()),
                        $required
                    )
                );
        }
    }

    /**
     * {@inheritdoc}
     */
    public static function handlesCuries(): array
    {
        return [
            SearchPromotionsRequestV1Mixin::findOne()->getCurie(),
        ];
    }
}
