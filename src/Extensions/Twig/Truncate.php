<?php

namespace Coa\VideolibraryBundle\Extensions\Twig;

use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

class Truncate extends AbstractExtension
{
    public function getFilters()
    {
        return [
            new TwigFilter('coa_videolibrary_truncate', [$this, 'truncate']),
        ];
    }

    public function truncate($value, $length = 200)
    {
        return mb_strimwidth($value, 0, $length, '...');
    }
}
