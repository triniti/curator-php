<?php
declare(strict_types=1);

namespace Triniti\Curator;

use Gdbots\Ncr\Aggregate;

class GalleryAggregate extends Aggregate
{
    /**
     * This is for legacy uses for command/event mixins for common
     * ncr operations. It will be removed in 3.x.
     *
     * @param string $name
     * @param array $arguments
     *
     * @return mixed
     */
    public function __call(string $name, array $arguments)
    {
        $newName = str_replace('Gallery', 'Node', $name);
        if ($newName !== $name && is_callable([$this, $newName])) {
            return $this->$newName(...$arguments);
        }
    }
}
