<?php
namespace Coa\VideolibraryBundle;

use Symfony\Component\HttpKernel\Bundle\Bundle;

class CoaVideolibraryBundle extends Bundle
{
    public function getPath(): string
    {
        return \dirname(__DIR__);
    }
}