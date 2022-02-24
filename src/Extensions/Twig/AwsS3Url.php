<?php

namespace Coa\VideolibraryBundle\Extensions\Twig;

use Coa\VideolibraryBundle\Entity\Video;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

class AwsS3Url extends AbstractExtension
{
    public function getFilters()
    {
        return [
            new TwigFilter('coaBucketBasename', [$this, 'urlBasename']),
        ];
    }

    public  function urlBasename(string $key, Video $video)
    {
        $bucket = $video->getBucket();
        $region = $video->getRegion();
        return "https://$bucket.s3.$region.amazonaws.com/$key";
    }
}