<?php

namespace MadisonSolutions\PHPTree;

class Registry
{
    /**
     * Index where parent relationships are stored.
     */
    private static $parents_index = [];

    /**
     * Index where child relationships are stored.
     */
    private static $children_index = [];

    /**
     * Index where keys are stored.
     */
    private static $keys_index = [];

    /**
     * Get the parent of an object.
     *
     * @param object $a The child object.
     * @return object|null The parent object of $a, or null if $a has no parent.
     */
    public static function parent(object $a)
    {
        return self::$parents_index[spl_object_id($a)] ?? null;
    }

    /**
     * Get the children of an object.
     *
     * @param object $a The parent object.
     * @return object[] Array of child objects.
     */
    public static function children(object $a)
    {
        return self::$children_index[spl_object_id($a)] ?? [];
    }

    /**
     * Get the key of an object, used by the parent to index the object.
     *
     * @param object $a The child object.
     * @return string|null The key for object $a, or null if $a has no parent
     */
    public static function key(object $a) : ?string
    {
        return self::$keys_index[spl_object_id($a)] ?? null;
    }

    /**
     * Check whether an object is an ancestor of another object.
     *
     * @param object $a The descendent object
     * @param object $b The object which might be an ancestor of $a
     * @return bool True if $b is an ancestor of $a, false otherwise.
     */
    public static function isAncestorOf(object $a, object $b) : bool
    {
        $a_id = spl_object_id($a);
        $curr_id = spl_object_id($b);
        while ($curr = @ self::$parents_index[$curr_id]) {
            $curr_id = spl_object_id($curr);
            if ($a_id === $curr_id) {
                return true;
            }
        }
        return false;
    }

    /**
     * Add a new child object to a given object, with the specified key
     *
     * @param object $parent The parent object.
     * @param string $key The key for the newly inserted child object. If the parent already has a child
     *    with the same key, the old node will be detached before the new node is inserted.
     * @param object $child The child object to be inserted. If the new node already has a parent, it will be
     *    detached from it's old parent first.
     * @throws CircularReferenceException If inserting the node would break the tree by creating a loop
     */
    public static function addChild(object $parent, string $key, object $child)
    {
        if (self::isAncestorOf($child, $parent)) {
            throw new CircularReferenceException();
        }
        $parent_id = spl_object_id($parent);
        $child_id = spl_object_id($child);
        // break any existing links

        self::detachChild($parent, $key);
        $old_parent = self::parent($child);
        if ($old_parent) {
            self::detachChild($old_parent, self::key($child));
        }
        // setup new links

        if (! isset(self::$children_index[$parent_id])) {
            self::$children_index[$parent_id] = [];
        }
        self::$children_index[$parent_id][$key] = $child;
        self::$parents_index[$child_id] = $parent;
        self::$keys_index[$child_id] = $key;
    }

    /**
     * Detach a child object from a parent object
     *
     * Detach the child node with the specified key, and return the detached object.
     * If the parent has no child with the specified key, no action is taken and null is returned.
     *
     * @param object $parent The parent object
     * @param string $key The key at which to detach a node.
     * @return object|null The detached object, or null if there was no child with the specified key.
     */
    public static function detachChild(object $parent, string $key) : ?object
    {
        $existing_child = @ self::children($parent)[$key];
        if ($existing_child) {
            $parent_id = spl_object_id($parent);
            $child_id = spl_object_id($existing_child);
            unset(self::$children_index[$parent_id][$key]);
            self::$parents_index[$child_id] = null;
            self::$keys_index[$child_id] = null;
        }
        return $existing_child;
    }

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
}
