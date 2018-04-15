<?php

namespace MadisonSolutions\PHPTree\Tests;

use PHPUnit\Framework\TestCase;
use MadisonSolutions\PHPTree\TreeNode;
use MadisonSolutions\PHPTree\Registry;
use MadisonSolutions\PHPTree\CircularReferenceException;

class TreeNodeTest extends TestCase
{
    public static function setUpBeforeClass()
    {
        include_once(__DIR__ . '/DummyTreeNode.php');
    }

    // helper method for verifying a child-parent relationship is as expected
    protected function assertChildParentKey(DummyTreeNode $child, ?DummyTreeNode $parent, ?string $key)
    {
        $this->assertSame($parent, $child->parent());
        $this->assertSame($key, $child->key());
        if ($parent) {
            $this->assertSame($child, $parent[$key]);
        }
    }

    // helper method of verifying the expected exception is thrown when the callback is executed
    protected function assertThrows(string $exceptionClass, callable $callback)
    {
        $e = null;
        try {
            $callback();
        } catch (\Exception $e) {
        }
        $this->assertInstanceOf($exceptionClass, $e);
    }

    public function testHierarchy()
    {
        $root = new DummyTreeNode();
        $a = new DummyTreeNode();
        $b = new DummyTreeNode();
        $c = new DummyTreeNode();
        $d = new DummyTreeNode();

        $this->assertSame([], $root->children());
        $this->assertChildParentKey($a, null, null);

        // add $a as a child of $root and verify hierarchy
        $root->addChild('a', $a);
        $this->assertSame(['a' => $a], $root->children());
        $this->assertChildParentKey($a, $root, 'a');

        // add $b as a child of $root and verify hierarchy
        $root->addChild('b', $b);
        $this->assertSame(['a' => $a, 'b' => $b], $root->children());
        $this->assertChildParentKey($b, $root, 'b');

        // create some childen of $a (grandchildren of root)
        $a->addChild('c', $c);
        $a->addChild('d', $d);
        $this->assertSame(['c' => $c, 'd' => $d], $a->children());
        $this->assertChildParentKey($c, $a, 'c');
        $this->assertChildParentKey($d, $a, 'd');
        $this->assertSame([$a, $root], $c->ancestors());
        $this->assertSame($root, $c->parent()->parent());
        $this->assertSame($root, $c->topAncestor());
        $this->assertTrue($root->isAncestorOf($a));
        $this->assertTrue($root->isAncestorOf($c));
        $this->assertTrue($a->isDescendentOf($root));
        $this->assertTrue($c->isDescendentOf($root));
        $this->assertFalse($b->isAncestorOf($a));
        $this->assertFalse($b->isDescendentOf($a));
        $this->assertSame([$a, $c, $d, $b], $root->descendents());
        $this->assertSame([$b], $a->siblings());

        // remove $a from the tree and verify separate hierarchy
        $a->detach();
        $this->assertChildParentKey($a, null, null);
        $this->assertSame(['b' => $b], $root->children());
        $this->assertSame([$a], $c->ancestors());
        $this->assertSame($a, $c->topAncestor());
        $this->assertFalse($root->isAncestorOf($a));
        $this->assertFalse($root->isAncestorOf($c));
        $this->assertFalse($a->isDescendentOf($root));
        $this->assertFalse($c->isDescendentOf($root));
        $this->assertTrue($a->isAncestorOf($c));
        $this->assertTrue($c->isDescendentOf($a));
        $this->assertSame([$b], $root->descendents());
        $this->assertSame([], $a->siblings());

        // add $a back to hierarchy in new position as child of $b
        $b->addChild('a', $a);
        $this->assertChildParentKey($a, $b, 'a');
        $this->assertSame(['a' => $a], $b->children());
        $this->assertSame([$a, $b, $root], $c->ancestors());
        $this->assertTrue($c->isDescendentOf($b));
        $this->assertSame([$b, $a, $c, $d], $root->descendents());

        // add child without detaching first - should detach and re-attach in the specified place
        $b->addChild('d', $d);
        $this->assertSame(['a' => $a, 'd' => $d], $b->children());
        $this->assertSame($b, $d->parent());
        $this->assertSame(['c' => $c], $a->children());
        $this->assertFalse($a->isAncestorOf($d));
        $this->assertTrue($b->isAncestorOf($d));
    }

    public function testArrayAccess()
    {
        // test you can get and set node children via array notation
        $root = new DummyTreeNode();
        $a = new DummyTreeNode();
        $root['a'] = $a;
        $this->assertSame($a, $root['a']);
        $this->assertSame(['a' => $a], $root->children());
        $this->assertChildParentKey($a, $root, 'a');
        $this->assertTrue(isset($root['a']));
        $this->assertFalse(isset($a['b']));
        $b = new DummyTreeNode();
        $a['b'] = $b;
        $this->assertSame($b, $a['b']);
        $this->assertSame($b, $root['a']['b']);
        $this->assertTrue(isset($a['b']));

        // test overwriting an existing key, the old value should be detached
        $b2 = new DummyTreeNode();
        $a['b'] = $b2;
        $this->assertSame($b2, $a['b']);
        $this->assertChildParentKey($b, null, null);
        $this->assertSame(['b' => $b2], $a->children());

        // test unsetting a key
        unset($a['b']);
        $this->assertChildParentKey($b2, null, null);
        $this->assertSame([], $a->children());
    }

    public function testPickWithArrays()
    {
        // test the pick method for getting nodes deep into the tree
        $root = new DummyTreeNode();

        $this->assertSame(null, $root->pick(['a']));

        $a = new DummyTreeNode('foo');
        $root->addChild('a', $a);

        $a2 = $root->pick(['a']);
        $this->assertSame($a2, $a);
        $this->assertSame('foo', $a2->value);

        $b = new DummyTreeNode();
        $a->addChild('b', $b);
        $this->assertSame($b, $a->pick(['b']));
        $this->assertSame($b, $root->pick(['a','b']));
    }

    public function testPickWithStrings()
    {
        // test the pick method for getting nodes deep into the tree
        $root = new DummyTreeNode();

        $this->assertSame(null, $root->pick('a'));

        $a = new DummyTreeNode('foo');
        $root->addChild('a', $a);

        $a2 = $root->pick('a');
        $this->assertSame($a2, $a);
        $this->assertSame('foo', $a2->value);

        $b = new DummyTreeNode();
        $a->addChild('b', $b);
        $this->assertSame($b, $a->pick('b'));
        $this->assertSame($b, $root->pick('a/b'));
    }

    public function testCircularReference()
    {
        // test exception is thrown if you try to create a circular reference
        $root = new DummyTreeNode();
        $root['a'] = $a = new DummyTreeNode();
        $a['b'] = $b = new DummyTreeNode();
        $this->assertThrows(CircularReferenceException::class, function () use ($root, $b) {
            $b->addChild('root', $root);
        });
    }

    public function testPaths()
    {
        $this->assertSame([], Registry::parsePath(''));
        $this->assertSame([], Registry::parsePath('/'));
        $this->assertSame([], Registry::parsePath(' / '));
        $this->assertSame(['0'], Registry::parsePath('0'));
        $this->assertSame(['0','0'], Registry::parsePath('0/0'));
        $this->assertSame(['0','0'], Registry::parsePath('/0/0/ '));
        $this->assertSame(['foo','bar'], Registry::parsePath('/foo/bar'));
        $this->assertSame(['foo','','bar'], Registry::parsePath('foo//bar'));

        $root = new DummyTreeNode();
        $root['a'] = $a = new DummyTreeNode();
        $a['b'] = $b = new DummyTreeNode();

        // test nodes know their own keys
        $this->assertSame('a', $a->key());
        $this->assertSame('b', $b->key());

        // test path() and breadcrumbs() methods
        $this->assertSame([$root, $a, $b], $b->breadcrumbs());
        $this->assertSame(['a','b'], $b->path());
        $this->assertSame([], $root->path());
    }

    public function testTraversal()
    {
        $root = new DummyTreeNode(0);
        $root['a'] = $a = new DummyTreeNode(1);
        $root['a']['b'] = $b = new DummyTreeNode(2);
        $root['a']['c'] = $c = new DummyTreeNode(3);

        // test forEachDeep method - the function below uses forEachDeep to traverse the tree
        // and return a string composed of details of each node visited
        $testFn = function ($node) {
            $out = '';
            $node->forEachDeep(function ($node, $relPath) use (&$out) {
                $out .= '('.implode('/', $relPath).':'.$node->value.')';
            });
            return $out;
        };
        $this->assertEquals('(:0)(a:1)(a/b:2)(a/c:3)', $testFn($root));
        $this->assertEquals('(:1)(b:2)(c:3)', $testFn($root['a']));
        $this->assertEquals('(:2)', $testFn($root['a']['b']));

        // test flatten()
        $this->assertEquals(['' => $root, 'a' => $a, 'a/b' => $b, 'a/c' => $c], $root->flatten());
        $this->assertEquals(['' => $a, 'b' => $b, 'c' => $c], $root['a']->flatten());
        $this->assertEquals(['' => $b], $root['a']['b']->flatten());
    }
}
