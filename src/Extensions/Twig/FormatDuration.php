<?php

namespace Coa\VideolibraryBundle\Extensions\Twig;

use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

class FormatDuration extends AbstractExtension
{
    public function getFilters()
    {
        return [
            new TwigFilter('coaSecToDuration', [$this, 'formatDuration']),
        ];
    }

    function formatDuration($seconds)
    {
        return gmdate('H:i:s', $seconds);
    }
}