<?php

namespace Trakli\Cloud\Contracts;

interface WorkspacePricing
{
    public function quote(int $members): array;
}
