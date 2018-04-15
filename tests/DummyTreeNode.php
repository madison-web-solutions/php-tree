<?php

namespace MadisonSolutions\PHPTree\Tests;

use MadisonSolutions\PHPTree\TreeNodeTrait;

class DummyTreeNode implements \ArrayAccess
{
    use TreeNodeTrait;

    public $value;

    public function __construct($value = null)
    {
        $this->value = $value;
    }
}
