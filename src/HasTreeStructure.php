<?php

namespace MadisonSolutions\PHPTree;

trait HasTreeStructure
{
    /**
     * Utility function for mapping an array of nodes to their objects
     */
    protected function mapToObjs($arrayOfNodes)
    {
        return array_map(function ($node) {
            return $node->obj;
        }, $arrayOfNodes);
    }

    protected $phptree_node;

    protected function getNode()
    {
        if (! $this->phptree_node) {
            $this->phptree_node = new TreeNode($this);
        }
        return $this->phptree_node;
    }

    public function parent() : ?self
    {
        $parentNode = $this->getNode()->parent();
        return $parentNode ? $parentNode->obj : null;
    }

    public function key() : ?string
    {
        return $this->getNode()->key();
    }

    public function children() : array
    {
        return $this->mapToObjs($this->getNode()->children());
    }

    public function addChild(string $key, self $obj) : self
    {
        $this->getNode()->addChild($key, $obj->getNode());
        return $this;
    }

    public function detachChild(string $key) : ?self
    {
        $old = $this->getNode()->detachChild($key);
        return $old ? $old->obj : null;
    }

    public function empty() : self
    {
        $this->getNode()->empty();
        return $this;
    }

    public function detach() : self
    {
        $this->getNode()->detach();
        return $this;
    }

    public function pick($path) : ?self
    {
        $picked = $this->getNode()->pick($path);
        return $picked ? $picked->obj : null;
    }

    public function replaceWith(self $replacement) : self
    {
        $this->getNode()->replaceWith($replacement->getNode());
        return $this;
    }

    public function isAncestorOf(self $a) : bool
    {
        return $this->getNode()->isAncestorOf($a->getNode());
    }

    public function isDescendentOf(self $a) : bool
    {
        return $this->getNode()->isDescendentOf($a->getNode());
    }

    public function ancestors() : array
    {
        return $this->mapToObjs($this->getNode()->ancestors());
    }

    public function topAncestor() : self
    {
        return $this->getNode()->topAncestor()->obj;
    }

    public function descendents() : array
    {
        return $this->mapToObjs($this->getNode()->descendents());
    }

    public function siblings() : array
    {
        return $this->mapToObjs($this->getNode()->siblings());
    }

    public function breadcrumbs() : array
    {
        return $this->mapToObjs($this->getNode()->breadcrumbs());
    }

    public function path() : array
    {
        return $this->getNode()->path();
    }

    public function forEachDeep(callable $fn) : self
    {
        $this->getNode()->forEachDeep(function ($node, $relPath) use ($fn) {
            return $fn($node->obj, $relPath);
        });
        return $this;
    }

    public function flatten() : array
    {
        return $this->mapToObjs($this->getNode()->flatten());
    }

    // ArrayAccess
    public function offsetSet($key, $value)
    {
        $this->addChild($key, $value);
    }
    public function offsetExists($key)
    {
        return array_key_exists($key, $this->getNode()->children());
    }
    public function offsetUnset($key)
    {
        $this->detachChild($key);
    }
    public function offsetGet($key)
    {
        $childNode = $this->getNode()->offsetGet($key);
        return $childNode ? $childNode->obj : null;
    }
}
