<?php
namespace Coa\VideolibraryBundle\Extensions\Twig;

use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

class FormatBytes extends AbstractExtension
{
    public function getFilters()
    {
        return [
            new TwigFilter('coaToBytes', [$this, 'formatBytes']),
        ];
    }

    function formatBytes($size, $precision = 2){
        $base = log($size, 1024);
        $suffixes = array('', 'K', 'M', 'G', 'T');
        return round(pow(1024, $base - floor($base)), $precision) .' '. $suffixes[floor($base)];
    }
}