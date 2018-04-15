<?php

namespace MadisonSolutions\PHPTree;

use MadisonSolutions\PHPTree\HasTreeStructure;

class StdTreeNode implements \ArrayAccess, \JsonSerializable
{
    use HasTreeStructure {
        addChild as protected traitHasChild;
    }

    public $value = null;
    protected $data = [];

    public function __construct($value = null, array $data = [])
    {
        $this->value = $value;
        $this->setData($data);
    }

    public function __clone()
    {
        throw new \Exception("Do not directly clone a TreeNode, use the clone() method");
    }

    public function __get($name)
    {
        switch ($name) {
            case 'parent':
                return $this->parent();
            case 'key':
                return $this->key();
            case 'children':
                return $this->children();
            default:
                return @ $this->data[$name];
        }
    }

    public function __isset($name)
    {
        switch ($name) {
            case 'parent':
            case 'key':
            case 'children':
                return true;
            default:
                return array_key_exists($name, $this->data);
        }
    }

    public function __set($name, $value)
    {
        switch ($name) {
            case 'parent':
            case 'key':
            case 'children':
                throw new \RuntimeException("Cannot directly set '{$name}' property of a TreeNode");
                break;
            default:
                $this->data[$name] = $value;
                break;
        }
    }
    public function __unset($name)
    {
        switch ($name) {
            case 'parent':
            case 'key':
            case 'children':
                throw new \RuntimeException("Cannot directly unset '{$name}' property of a TreeNode");
                break;
            default:
                unset($this->data[$name]);
                break;
        }
    }

    public function setData(array $data)
    {
        foreach ($data as $name => $value) {
            $this->__set($name, $value);
        }
        return $this;
    }

    public function setChildren(array $children)
    {
        $this->empty();
        foreach ($children as $key => $child) {
            $this->addChild($key, $child);
        }
        return $this;
    }

    public function addChild(string $key, $obj) : self
    {
        if (!($obj instanceof StdTreeNode)) {
            $obj = new StdTreeNode($obj);
        }
        return $this->traitHasChild($key, $obj);
    }

    public function childOrCreate($key, $default_value = null)
    {
        $child = @ $this->children[$key];
        if (! $child) {
            $child = new StdTreeNode($default_value);
            $this->addChild($key, $child);
        }
        return $child;
    }

    public function pickOrCreate($path, $default_value = null)
    {
        $keys = TreeNode::parsePath($path);
        if (empty($keys)) {
            return $this;
        } else {
            $next_key = array_shift($keys);
            $next_node = $this->childOrCreate($next_key, $default_value);
            return $next_node->pickOrCreate($keys, $default_value);
        }
    }

    public function putValue($path, $value, $data = null)
    {
        $node = $this->pickOrCreate($path);
        $node->value = $value;
        if ($data) {
            $node->setData($data);
        }
        return $node;
    }

    public function putNode($path, StdTreeNode $node)
    {
        $existing = $this->pickOrCreate($path);
        if ($existing === $this) {
            throw new \Exception("putNode called with empty path");
        }
        // replace existing with new node
        $existing->parent->addChild($existing->key, $node);
        return $this;
    }

    public function map(callable $fn) : StdTreeNode
    {
        $relPath = [];
        $recurse = function ($node) use (&$recurse, &$relPath, $fn) {
            $mapped = new StdTreeNode();
            $fn($mapped, $node, $relPath);
            foreach ($node->children as $key => $child) {
                $relPath[] = $key;
                $mapped->addChild($key, $recurse($child));
                array_pop($relPath);
            }
            return $mapped;
        };
        return $recurse($this);
    }

    public function toArray()
    {
        return [
            'value' => $this->value,
            'data' => $this->data,
            'children' => $this->children,
        ];
    }

    public function jsonSerialize()
    {
        // @todo - should we maybe check that objects saved in value or data are also JsonSerializable ?
        return $this->toArray();
    }

    public static function fromArray(array $arr)
    {
        $node = new StdTreeNode();
        foreach ($arr as $name => $value) {
            switch ($name) {
                case 'value':
                    $node->value = $value;
                    break;
                case 'children':
                    foreach ($value as $key => $childArr) {
                        $node->addChild($key, StdTreeNode::fromArray($childArr));
                    }
                    break;
                case 'data':
                    $node->setData($value);
                    break;
            }
        }
        return $node;
    }

    public static function fromJson(string $json)
    {
        $arr = json_decode($json, true);
        $err = json_last_error();
        if (is_array($arr) && $err == JSON_ERROR_NONE) {
            return StdTreeNode::fromArray($arr);
        } else {
            throw new \Exception("Invalid JSON");
        }
    }

    public function clone()
    {
        return StdTreeNode::fromJson(json_encode($this));
    }

    public function flattenValues()
    {
        return array_map(function ($node) {
            return $node->value;
        }, $this->flatten());
    }
}
