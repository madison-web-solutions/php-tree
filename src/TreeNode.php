<?php

namespace MadisonSolutions\PHPTree;

class TreeNode implements \ArrayAccess
{
    /**
     * Split a slash-separated path string into an array of components.
     *
     * If an array is provided it is returned unchanged.
     *
     * @param string|array $path Path string (or array of path components).
     * @return array Array of path components.
     * @throws \InvalidArgumentException if the argument is not a string or an array.
     */
    public static function parsePath($path) : array
    {
        if (is_string($path)) {
            $path = trim(trim($path), '/');
            $path = ($path === '') ? [] : explode('/', $path);
        }
        if (! is_array($path)) {
            throw new \InvalidArgumentException("Tree Node path must be string or array");
        }
        return $path;
    }

    /**
     * @var mixed The object or data at this position in the tree.
     */
    public $obj = null;

    /**
     * @var TreeNode|null This node's parent node in the tree,
     *     or null if this node is the root of the tree.
     */
    protected $parent = null;

    /**
     * @var string|null This node's key, which is how this node is indexed from its parent,
     *    or null if this node has no parent.
     */
    protected $key = null;

    /**
     * @var TreeNode[] Array of this node's child nodes, indexed by the child nodes keys.
     */
    protected $children = [];

    /**
     * Create a new TreeNode
     *
     * @param mixed $obj The object or data at this position in the tree.
     */
    public function __construct($obj = null)
    {
        $this->obj = $obj;
    }

    /**
     * Get the parent node
     *
     * @return TreeNode|null The parent node or null if this node is the root of the tree.
     */
    public function parent() : ?TreeNode
    {
        return $this->parent;
    }

    /**
     * Get this node's key
     *
     * @return string|null This node's key, or null if this node has no parent.
     */
    public function key() : ?string
    {
        return $this->key;
    }

    /**
     * Get this node's children
     *
     * @return TreeNode[] Array of child nodes, indexed by the child nodes keys.
     */
    public function children() : array
    {
        return $this->children;
    }

    /**
     * Add a new child node to this node, with the specified key
     *
     * @param string $key The key for the newly inserted child node. If this node already has a child
     *    with the same key, the old node will be detached before the new node is inserted.
     * @param TreeNode $node The node to be inserted. If the new node already has a parent, it will be
     *    detached from it's old parent first.
     * @return TreeNode The current node is returned for chaining.
     * @throws CircularReferenceException If inserting the node would break the tree by creating a loop
     */
    public function addChild(string $key, TreeNode $node) : TreeNode
    {
        if ($node->isAncestorOf($this)) {
            throw new CircularReferenceException();
        }
        // break any existing links
        $this->detachChild($key);
        $node->detach();
        // setup new links
        $this->children[$key] = $node;
        $node->parent = $this;
        $node->key = $key;
        return $this;
    }

    /**
     * Detach one of this node's children.
     *
     * Detach the child node with the specified key, and return the detached node.
     * If this node has no child with the specified key, no action is taken and null returned.
     *
     * @param string $key The key at which to detach a node.
     * @return TreeNode|null The detached node, or null if there was no child with the specified key.
     */
    public function detachChild(string $key) : ?TreeNode
    {
        $existing = @ $this->children[$key];
        if ($existing) {
            unset($this->children[$key]);
            $existing->parent = null;
            $existing->key = null;
        }
        return $existing;
    }

    /**
     * Detach all of this node's children.
     *
     * @return TreeNode The current node is returned for chaining.
     */
    public function empty() : TreeNode
    {
        foreach ($this->children as $key => $child) {
            $this->detachChild($key);
        }
        return $this;
    }

    /**
     * Detach this node from it's parent (if it has one).
     *
     * @return TreeNode The current node is returned for chaining.
     */
    public function detach() : TreeNode
    {
        if ($this->parent) {
            $this->parent->detachChild($this->key);
        }
        return $this;
    }

    /**
     * Get the node at the given relative path.
     *
     * Looks for a node at the given path relative to this node, and returns it.
     * If there is no node with the specified path, null is retured.
     * eg `$node->pick('a/2')` will look for a child with key 'a' then a grandchild with key '2'.
     *
     * @param string|array $path Slash-separated path string, or array of path components.
     * @return TreeNode|null Node at the specified path, or null if the path doesn't exist.
     */
    public function pick($path) : ?TreeNode
    {
        $keys = TreeNode::parsePath($path);
        if (empty($keys)) {
            return $this;
        } else {
            $next_key = array_shift($keys);
            $next_node = $this->children[$next_key] ?? null;
            return $next_node ? $next_node->pick($keys) : null;
        }
    }

    /**
     * Detach this node from the tree and replace it with another with the same key.
     *
     * @param TreeNode The new node which will replace this one.
     * @return TreeNode The current node is returned for chaining.
     */
    public function replaceWith(TreeNode $replacement) : TreeNode
    {
        if ($this->parent) {
            $this->parent->addChild($this->key, $replacement);
        }
        return $this;
    }

    /**
     * Check whether a node is an ancestor of this node.
     *
     * @param TreeNode $a The node to check.
     * @return bool True if $a is an ancestor of this node, false otherwise.
     */
    public function isAncestorOf(TreeNode $a) : bool
    {
        while ($a = $a->parent) {
            if ($a === $this) {
                return true;
            }
        }
        return false;
    }

    /**
     * Check whether a node is an descendent of this node.
     *
     * @param TreeNode $a The node to check.
     * @return bool True if $a is an descendent of this node, false otherwise.
     */
    public function isDescendentOf(TreeNode $a) : bool
    {
        return $a ? $a->isAncestorOf($this) : false;
    }

    /**
     * Get an array of this node's ancestors
     *
     * Ancestors are returned in a flat, numerically indexed array, starting with the immediate parent,
     * and ending with the root node.
     *
     * @return TreeNode[] Array of this node's ancestors.
     */
    public function ancestors() : array
    {
        $ancestors = array();
        $a = $this;
        while ($a = $a->parent) {
            $ancestors[] = $a;
        }
        return $ancestors;
    }

    /**
     * Get this node's top ancestor, ie the root node of this tree.
     *
     * @return TreeNode The root node of this tree.
     */
    public function topAncestor() : TreeNode
    {
        $a = $this;
        while ($a->parent) {
            $a = $a->parent;
        }
        return $a;
    }

    /**
     * Get an array of this node's descendents.
     *
     * Descendents are returned in a flat, numerically indexed array, using a depth-first ordering.
     *
     * @return TreeNode[] Array of this node's descendents.
     */
    public function descendents() : array
    {
        $descendents = [];
        $recurse = function ($node) use (&$descendents, &$recurse) {
            foreach ($node->children as $child) {
                $descendents[] = $child;
                $recurse($child);
            }
        };
        $recurse($this);
        return $descendents;
    }

    /**
     * Get an array of this node's siblings.
     *
     * Siblings are returned in a numerically indexed array.
     * The current node is not included in the output.
     *
     * @return TreeNode[] Array of this node's siblings.
     */
    public function siblings() : array
    {
        if (! $this->parent) {
            return [];
        }
        $siblings = [];
        foreach ($this->parent->children as $child) {
            if ($child !== $this) {
                $siblings[] = $child;
            }
        }
        return $siblings;
    }

    /**
     * Get an array containing this node and it's ancestors, starting with the root node.
     *
     * return TreeNode[] Array of 'breadcrumbs' to this node.
     */
    public function breadcrumbs() : array
    {
        return array_reverse(array_merge([$this], $this->ancestors()));
    }

    /**
     * Get the 'path' to this node from the root node of this tree.
     *
     * @return string[] Array of keys identifying the path to this node from the root.
     */
    public function path() : array
    {
        $path = [];
        $a = $this;
        while ($a->parent) {
            array_unshift($path, $a->key);
            $a = $a->parent;
        }
        return $path;
    }

    /**
     * Apply a function to this node and each of it's descendents.
     *
     * Walk through the sub-tree consisting of this node and all it's descendents, and apply the given
     * Function at each visited node.
     * The function will be called with 2 arguments:
     *  1. The node currently visited.
     *  2. The 'relative path' to the visited node from this node, as an array of keys.
     *
     * @param callable $fn The function to apply at each node in the subtree.
     * @return TreeNode The current node is returned for chaining.
     */
    public function forEachDeep(callable $fn) : TreeNode
    {
        $relPath = [];
        $recurse = function ($node) use (&$recurse, &$relPath, $fn) {
            $fn($node, $relPath);
            foreach ($node->children as $key => $child) {
                $relPath[] = $key;
                $recurse($child);
                array_pop($relPath);
            }
        };
        $recurse($this);
        return $this;
    }

    /**
     * Get a 'flattened' array of the nodes of this sub-tree
     *
     * The returned array will contain this node and all it's descendents, indexed by relative path.
     * Note the index for this node will always be the empty string.
     * The index for descendent nodes will be a slash-sparated path string.
     *
     * return TreeNode[] Flattened array of nodes
     */
    public function flatten() : array
    {
        $out = [];
        $this->forEachDeep(function ($node, $relPath) use (&$out) {
            $out[implode('/', $relPath)] = $node;
        });
        return $out;
    }

    /**** Implementation of ArrayAccess interface ****/

    public function offsetSet($key, $value)
    {
        $this->addChild($key, $value);
    }
    public function offsetExists($key)
    {
        return array_key_exists($key, $this->children);
    }
    public function offsetUnset($key)
    {
        $this->detachChild($key);
    }
    public function offsetGet($key)
    {
        return $this->children[$key] ?? null;
    }
}
