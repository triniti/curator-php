<?php
declare(strict_types=1);

namespace Triniti\Curator\Exception;

use Gdbots\Pbj\Exception\HasEndUserMessage;
use Gdbots\Schemas\Pbjx\Enum\Code;

final class TargetNotPublished extends \RuntimeException implements TrinitiCuratorException, HasEndUserMessage
{
    public function __construct(string $message = 'Target not published.')
    {
        parent::__construct($message, Code::FAILED_PRECONDITION);
    }

    public function getEndUserMessage()
    {
        return $this->getMessage();
    }

    public function getEndUserHelpLink()
    {
        return null;
    }
}
