<?php

namespace MadisonSolutions\PHPTree;

class StdTreeNode implements \ArrayAccess, \JsonSerializable
{
    use TreeNodeTrait {
        addChild as protected traitAddChild;
    }

    public $value = null;
    protected $data = [];

    /**
     * Create a new StdTreeNode object
     *
     * @param mixed $value The node's value
     * @param array $data Optional array of metadata for this node
     */
    public function __construct($value = null, array $data = [])
    {
        $this->value = $value;
        $this->setData($data);
    }

    public function __clone()
    {
        throw new \Exception("Do not directly clone a TreeNode, use the clone() method");
    }

    /**
     * Access 'magic' properties.
     *
     * If the property name is 'parent', 'key' or 'children' then the relevant TreeNodeTrait function is called.
     * Otherwise we assume the property is the name of a variable set in the meta data array.
     */
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

    /**
     * Check which 'magic' properties are set.
     *
     * If the property name is 'parent', 'key' or 'children' then the relevant TreeNodeTrait function is called.
     * Otherwise we assume the property is the name of a variable set in the meta data array.
     */
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

    /**
     * Set 'magic' properties.
     *
     * If the property name is 'parent', 'key' or 'children' then an exception is thrown because these
     * are read only properties. Otherwise we set the value in the meta data array.
     */
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

    /**
     * Unset 'magic' properties.
     *
     * If the property name is 'parent', 'key' or 'children' then an exception is thrown because these
     * are read only properties. Otherwise we unset the value in the meta data array.
     */
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

    /**
     * Set meta data on the object.
     *
     * Each name/value pair in the $data array will be saved as meta data to this object.
     *
     * @param array $data Array of name/value pairs of meta data to save.
     * @return self The current object is returned for chaining.
     */
    public function setData(array $data)
    {
        foreach ($data as $name => $value) {
            $this->__set($name, $value);
        }
        return $this;
    }

    /**
     * Set this object's children.
     *
     * All existing children are first detached, then the new children are added.
     *
     * @param array $children Array of child nodes to add (array keys are preserved)
     * @return self The current object is returned for chaining.
     */
    public function setChildren(array $children)
    {
        $this->empty();
        foreach ($children as $key => $child) {
            $this->addChild($key, $child);
        }
        return $this;
    }

    /**
     * Add a new child object with the given key
     *
     * @param string $key The key for the newly inserted child node. If this node already has a child
     *    with the same key, the old node will be detached before the new node is inserted.
     * @param mixed $node The node to be inserted. If the new node already has a parent, it will be
     *    detached from it's old parent first. If the supplied object is not an StdTreeNode object, then a
     *    new StdTreeNode object will be created with $obj as it's value, and that inserted into the tree.
     * @return self The current node is returned for chaining.
     * @throws CircularReferenceException If inserting the node would break the tree by creating a loop
     */
    public function addChild(string $key, $obj) : self
    {
        if (!($obj instanceof StdTreeNode)) {
            $obj = new StdTreeNode($obj);
        }
        return $this->traitAddChild($key, $obj);
    }

    /**
     * Return or create the child node with the given key.
     *
     * If the node has a child with the given key, return it.
     * Otherwise create a new child node with that key, insert it, and return it.
     *
     * @param string $key The key for the newly inserted child node.
     * @param mixed $default_value Optional default value for the new child node if the key isn't present
     */
    public function childOrCreate($key, $default_value = null)
    {
        $child = @ $this->children[$key];
        if (! $child) {
            $child = new StdTreeNode($default_value);
            $this->addChild($key, $child);
        }
        return $child;
    }

    /**
     * Return or create the node at the given relative path.
     *
     * If the node has a descendent at the specified path, return it.
     * Otherwise create and insert child nodes to create the specified path, and return the
     * newly created node at the specified path.
     *
     * @param string|array $path Slash-separated path string, or array of path components.
     * @param mixed $default_value Optional default value for any new descendent nodes which are created.
     */
    public function pickOrCreate($path, $default_value = null)
    {
        $keys = Registry::parsePath($path);
        if (empty($keys)) {
            return $this;
        } else {
            $next_key = array_shift($keys);
            $next_node = $this->childOrCreate($next_key, $default_value);
            return $next_node->pickOrCreate($keys, $default_value);
        }
    }

    /**
     * Set the value and data for the node at the given relative path, and return the node.
     *
     * If the path doesn't yet exist, then intermediate nodes will be created along the way (with null value)
     *
     * @param string|array $path Slash-separated path string, or array of path components.
     * @param mixed $value The node's value.
     * @param array $data Optional array of metadata for this node.
     * @return StdTreeNode The node at the specified relative path.
     */
    public function putValue($path, $value, $data = null)
    {
        $node = $this->pickOrCreate($path);
        $node->value = $value;
        if ($data) {
            $node->setData($data);
        }
        return $node;
    }

    /**
     * Insert the given node at the given relative path.
     *
     * If the path doesn't yet exist, then intermediate nodes will be created along the way (with null value)
     *
     * @param string|array $path Slash-separated path string, or array of path components.
     * @param StdTreeNode $node The node to insert
     * @return self The current node is returned for chaining.
     */
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

    /**
     * Apply the given function to each node of the current sub-tree to create a new tree with the mapped values.
     *
     * The callback function will be called with the following arguments
     * 1. The newly created StdTreeNode (the image of the mapping)
     * 2. The exising StdTreeNode from the current sub-tree
     * 3. The relative path
     *
     * @param callabke $fn The function to apply to each node of the sub-tree
     * @return StdTreeNode The root node of the mapped tree
     */
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

    /**
     * Convert to array format
     */
    public function toArray()
    {
        return [
            'value' => $this->value,
            'data' => $this->data,
            'children' => $this->children,
        ];
    }

    /**
     * Return data which will be used for json serialization
     */
    public function jsonSerialize()
    {
        // @todo - should we maybe check that objects saved in value or data are also JsonSerializable ?
        return $this->toArray();
    }

    /**
     * Construct a new tree from the given (nested) array
     */
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

    /**
     * Construct a new tree from the given json serialization
     */
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

    /**
     * Construct a new tree with the same structure, values and meta data as this one
     */
    public function clone()
    {
        return StdTreeNode::fromJson(json_encode($this));
    }

    /**
     * Return an array of the values of the current sub-tree, indexed by path
     */
    public function flattenValues()
    {
        return array_map(function ($node) {
            return $node->value;
        }, $this->flatten());
    }
}
