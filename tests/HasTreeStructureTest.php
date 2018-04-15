<?php

namespace MadisonSolutions\Structures\Tests;

use PHPUnit\Framework\TestCase;
use MadisonSolutions\PHPTree\StdTreeNode;
use MadisonSolutions\PHPTree\CircularReferenceException;

class HasTreeStructureTest extends TestCase
{
    // helper method for verifying a child-parent relationship is as expected
    protected function assertChildParentKey(StdTreeNode $child, ?StdTreeNode $parent, ?string $key)
    {
        $this->assertSame($parent, $child->parent);
        $this->assertSame($key, $child->key);
        if ($parent) {
            $this->assertSame($child, $parent->children[$key]);
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

    public function testEmptyNode()
    {
        // Create an orphan node with no value or data
        $node = new StdTreeNode();
        $this->assertInstanceOf(StdTreeNode::class, $node);
        $this->assertSame(null, $node->value);
        $this->assertSame(null, $node->parent);
        $this->assertSame([], $node->children);
    }

    public function testNodeValues()
    {
        // Test storing values on nodes
        $node = new StdTreeNode(null);
        $this->assertSame(null, $node->value);

        $node = new StdTreeNode('foo');
        $this->assertSame('foo', $node->value);

        $node = new StdTreeNode(10);
        $this->assertSame(10, $node->value);

        $node = new StdTreeNode(false);
        $this->assertSame(false, $node->value);

        // Test changing the value
        unset($node->value);
        $this->assertSame(null, $node->value);
        $node->value = true;
        $this->assertSame(true, $node->value);
        $node->value = 'foo';
        $this->assertSame('foo', $node->value);
    }

    public function testNodeData()
    {
        // Test storing meta-data on nodes
        $node = new StdTreeNode(1, ['foo' => 'bar', 'derp' => true]);
        $this->assertSame(1, $node->value);
        $this->assertSame('bar', $node->foo);
        $this->assertSame(true, $node->derp);
        $this->assertSame(null, $node->arp);

        // Test updating node meta-data via setData()
        $this->assertSame($node, $node->setData(['arp' => 'Y']));
        $this->assertSame('Y', $node->arp);
        $node->setData(['derp' => false]);
        $this->assertSame(false, $node->derp);
        $node->setData(['foo' => null]);
        $this->assertSame(null, $node->foo);

        // Test updating noded meta-data by directly setting properties
        $node->foo = 'bar';
        $this->assertTrue(isset($node->foo));
        $this->assertSame('bar', $node->foo);
        unset($node->foo);
        $this->assertFalse(isset($node->foo));
        $this->assertSame(null, $node->foo);

        // The property names parent, key and childen are reserved, and cannot be directly or via setData
        $this->assertThrows(\RuntimeException::class, function () use ($node) {
            $node->setData(['parent' => 'foo']);
        });
        $this->assertThrows(\RuntimeException::class, function () use ($node) {
            $node->setData(['key' => 'foo']);
        });
        $this->assertThrows(\RuntimeException::class, function () use ($node) {
            $node->setData(['children' => 'foo']);
        });
        $this->assertThrows(\RuntimeException::class, function () use ($node) {
            $node->parent = new StdTreeNode();
        });
        $this->assertThrows(\RuntimeException::class, function () use ($node) {
            $node->key = '1';
        });
        $this->assertThrows(\RuntimeException::class, function () use ($node) {
            $node->children = ['1' => new StdTreeNode()];
        });
    }

    public function testHierarchy()
    {
        $root = new StdTreeNode();
        $a = new StdTreeNode();
        $b = new StdTreeNode();
        $c = new StdTreeNode();
        $d = new StdTreeNode();

        $this->assertSame([], $root->children);
        $this->assertChildParentKey($a, null, null);

        // add $a as a child of $root and verify hierarchy
        $root->addChild('a', $a);
        $this->assertSame(['a' => $a], $root->children);
        $this->assertChildParentKey($a, $root, 'a');

        // add $b as a child of $root and verify hierarchy
        $root->addChild('b', $b);
        $this->assertSame(['a' => $a, 'b' => $b], $root->children);
        $this->assertChildParentKey($b, $root, 'b');

        // create some childen of $a (grandchildren of root)
        $a->setChildren(['c' => $c, 'd' => $d]);
        $this->assertSame(['c' => $c, 'd' => $d], $a->children);
        $this->assertChildParentKey($c, $a, 'c');
        $this->assertChildParentKey($d, $a, 'd');
        $this->assertSame([$a, $root], $c->ancestors());
        $this->assertSame($root, $c->parent->parent);
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
        $this->assertSame(['b' => $b], $root->children);
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
        $this->assertSame(['a' => $a], $b->children);
        $this->assertSame([$a, $b, $root], $c->ancestors());
        $this->assertTrue($c->isDescendentOf($b));
        $this->assertSame([$b, $a, $c, $d], $root->descendents());

        // add child without detaching first - should detach and re-attach in the specified place
        $b->addChild('d', $d);
        $this->assertSame(['a' => $a, 'd' => $d], $b->children);
        $this->assertSame($b, $d->parent);
        $this->assertSame(['c' => $c], $a->children);
        $this->assertFalse($a->isAncestorOf($d));
        $this->assertTrue($b->isAncestorOf($d));

        // can't directly set a parent
        $this->assertThrows(\RuntimeException::class, function () use ($a, $d) {
            $a->parent = $d;
        });
        $this->assertChildParentKey($a, $b, 'a');
    }

    public function testArrayAccess()
    {
        // test you can get and set node children via array notation
        $root = new StdTreeNode();
        $a = new StdTreeNode();
        $root['a'] = $a;
        $this->assertSame($a, $root['a']);
        $this->assertSame(['a' => $a], $root->children);
        $this->assertChildParentKey($a, $root, 'a');
        $this->assertTrue(isset($root['a']));
        $this->assertFalse(isset($a['b']));
        $b = new StdTreeNode();
        $a['b'] = $b;
        $this->assertSame($b, $a['b']);
        $this->assertSame($b, $root['a']['b']);
        $this->assertTrue(isset($a['b']));

        // test overwriting an existing key, the old value should be detached
        $b2 = new StdTreeNode();
        $a['b'] = $b2;
        $this->assertSame($b2, $a['b']);
        $this->assertChildParentKey($b, null, null);
        $this->assertSame(['b' => $b2], $a->children);

        // test unsetting a key
        unset($a['b']);
        $this->assertChildParentKey($b2, null, null);
        $this->assertSame([], $a->children);

        // test implicit creation of a node by setting a scalar value
        $root['foo'] = 'bar';
        $this->assertInstanceOf(StdTreeNode::class, $root['foo']);
        $this->assertSame('bar', $root['foo']->value);
    }

    public function testPutPickWithArrays()
    {
        // test the pick and put methods for manipulating nodes deep into the tree
        $root = new StdTreeNode();

        $this->assertSame(null, $root->pick(['a']));

        $a = $root->putValue(['a'], 'foo');
        $this->assertInstanceOf(StdTreeNode::class, $a);
        $this->assertChildParentKey($a, $root, 'a');
        $this->assertSame(['a' => $a], $root->children);
        $this->assertSame('foo', $a->value);

        $a2 = $root->pick(['a']);
        $this->assertSame($a2, $a);
        $this->assertSame('foo', $a2->value);

        $a3 = $root->putValue(['a'], 'bar');
        $this->assertSame($a3, $a);
        $this->assertSame('bar', $a->value);
        $this->assertSame('bar', $a3->value);

        $b = $root->putValue(['a','b'], null);
        $this->assertInstanceOf(StdTreeNode::class, $b);
        $this->assertChildParentKey($a, $root, 'a');
        $this->assertChildParentKey($b, $a, 'b');
        $this->assertSame(['b' => $b], $a->children);
        $this->assertSame($b, $a->pick(['b']));
        $this->assertSame($b, $root->pick(['a','b']));

        // test adding a node with parents that don't exist - the intermediate nodes should be created with null values
        $c = $root->putValue(['a','0','0','c'], null);
        $this->assertInstanceOf(StdTreeNode::class, $c);
        $this->assertSame($c, $root->pick(['a','0','0','c']));
        $this->assertInstanceOf(StdTreeNode::class, $a[0]);
        $this->assertSame(null, $a[0]->value);
        $this->assertSame(null, $a[0][0]->value);
        $this->assertSame($c, $a[0][0]['c']);
        $this->assertSame($a, $c->parent->parent->parent);

        // pick on a node which doesn't exist should return null
        $this->assertSame(null, $root->pick(['a','1']));
        // pickOrCreate will create a missing node instead
        $d = $root->pickOrCreate(['a','1']);
        $this->assertInstanceOf(StdTreeNode::class, $d);
        $this->assertChildParentKey($d, $a, '1');

        // test putting a node instead of a value - should replace the existing node
        $d2 = new StdTreeNode('second');
        $root->putNode(['a','1'], $d2);
        $this->assertSame('second', $root->pick(['a','1'])->value);
        $this->assertChildParentKey($d2, $a, '1');
        $this->assertChildParentKey($d, null, null);

        // shouldn't allow putting with an empty path...
        $d3 = new StdTreeNode('third');
        $this->assertThrows(\Exception::class, function () use ($d2, $d3) {
            $d2->putNode([], $d3);
        });
        $this->assertSame('second', $root->pick(['a','1'])->value);
        $this->assertChildParentKey($d2, $a, '1');
        $this->assertChildParentKey($d3, null, null);

        // ...for that we need the replaceWith method
        $d2->replaceWith($d3);
        $this->assertSame('third', $root->pick(['a','1'])->value);
        $this->assertChildParentKey($d2, null, null);
        $this->assertChildParentKey($d3, $a, '1');
    }

    public function testPutPickWithStrings()
    {
        // test the pick and put methods for manipulating nodes deep into the tree
        $root = new StdTreeNode();

        $this->assertSame($root, $root->pick(''));
        $this->assertSame($root, $root->pick('/'));

        $this->assertSame(null, $root->pick('a'));

        $a = $root->putValue('a', 'foo');
        $this->assertInstanceOf(StdTreeNode::class, $a);
        $this->assertChildParentKey($a, $root, 'a');
        $this->assertSame(['a' => $a], $root->children);
        $this->assertSame('foo', $a->value);

        $a2 = $root->pick('a');
        $this->assertSame($a2, $a);
        $this->assertSame('foo', $a2->value);

        $a3 = $root->putValue('a', 'bar');
        $this->assertSame($a3, $a);
        $this->assertSame('bar', $a->value);
        $this->assertSame('bar', $a3->value);

        $b = $root->putValue('a/b', null);
        $this->assertInstanceOf(StdTreeNode::class, $b);
        $this->assertChildParentKey($a, $root, 'a');
        $this->assertChildParentKey($b, $a, 'b');
        $this->assertSame(['b' => $b], $a->children);
        $this->assertSame($b, $a->pick('b'));
        $this->assertSame($b, $root->pick('a/b'));

        // test adding a node with parents that don't exist - the intermediate nodes should be created with null values
        $c = $root->putValue('a/0/0/c', null);
        $this->assertInstanceOf(StdTreeNode::class, $c);
        $this->assertSame($c, $root->pick('a/0/0/c'));
        $this->assertInstanceOf(StdTreeNode::class, $a[0]);
        $this->assertSame(null, $a[0]->value);
        $this->assertSame(null, $a[0][0]->value);
        $this->assertSame($c, $a[0][0]['c']);
        $this->assertSame($a, $c->parent->parent->parent);

        // pick on a node which doesn't exist should return null
        $this->assertSame(null, $root->pick('a/1'));
        // pickOrCreate will create a missing node instead
        $d = $root->pickOrCreate('a/1');
        $this->assertInstanceOf(StdTreeNode::class, $d);
        $this->assertChildParentKey($d, $a, '1');

        // test putting a node instead of a value - should replace the existing node
        $d2 = new StdTreeNode('second');
        $root->putNode('a/1', $d2);
        $this->assertSame('second', $root->pick('a/1')->value);
        $this->assertChildParentKey($d2, $a, '1');
        $this->assertChildParentKey($d, null, null);

        // shouldn't allow putting with an empty path...
        $d3 = new StdTreeNode('third');
        foreach ([null, '', '/', ' / '] as $path) {
            $this->assertThrows(\Exception::class, function () use ($d2, $d3) {
                $d2->putNode($path, $d3);
            });
            $this->assertSame('second', $root->pick(['a','1'])->value);
            $this->assertChildParentKey($d2, $a, '1');
            $this->assertChildParentKey($d3, null, null);
        }
    }

    public function testCircularReference()
    {
        // test exception is thrown if you try to create a circular reference
        $root = new StdTreeNode();
        $a = $root->putValue('a', null);
        $b = $root->putValue('a/b', null);
        $this->assertThrows(CircularReferenceException::class, function () use ($root, $b) {
            $b->addChild('root', $root);
        });
    }

    public function testPaths()
    {
        $root = new StdTreeNode();
        $a = $root->putValue('a', null);
        $b = $root->putValue('a/b', null);

        // test nodes know their own keys
        $this->assertSame('a', $a->key);
        $this->assertSame('b', $b->key);

        // test path() and breadcrumbs() methods
        $this->assertSame([$root, $a, $b], $b->breadcrumbs());
        $this->assertSame(['a','b'], $b->path());
        $this->assertSame([], $root->path());
    }

    public function testJsonSerialize()
    {
        $root = new StdTreeNode(null, ['foo' => true]);
        $root['a'] = new StdTreeNode(1, ['num_cats' => 2, 'names' => ['Gary', 'Waffles']]);
        $root['a']['b'] = new StdTreeNode(2);
        $root['a']['c'] = new StdTreeNode(3);

        // Test encoding node into json
        $json = json_encode($root);
        $expected = json_encode([
            "value" => null,
            "data" => [
                "foo" => true,
            ],
            "children" => [
                "a" => [
                    "value" => 1,
                    "data" => [
                        "num_cats" => 2,
                        "names" => ["Gary", "Waffles"],
                    ],
                    "children" => [
                        "b" => [
                            "value" => 2,
                            "data" => [],
                            "children" => [],
                        ],
                        "c" => [
                            "value" => 3,
                            "data" => [],
                            "children" => [],
                        ],
                    ],
                ],
            ]
        ]);
        $this->assertSame($expected, $json);

        // test decoding node from json
        $copy = StdTreeNode::fromJson($json);
        $this->assertEquals($root, $copy);
    }

    public function testTraversal()
    {
        $root = new StdTreeNode(0, ['foo' => true]);
        $root['a'] = new StdTreeNode(1, ['foo' => false]);
        $root['a']['b'] = new StdTreeNode(2, ['foo' => true]);
        $root['a']['c'] = new StdTreeNode(3, ['foo' => false]);

        // test forEachDeep method - the function below uses forEachDeep to traverse the tree
        // and return a string composed of details of each node visited
        $testFn = function ($node) {
            $out = '';
            $node->forEachDeep(function ($node, $relPath) use (&$out) {
                $out .= '('.implode('/', $relPath).':'.$node->value.':'.($node->foo?'t':'f').')';
            });
            return $out;
        };
        $this->assertEquals('(:0:t)(a:1:f)(a/b:2:t)(a/c:3:f)', $testFn($root));
        $this->assertEquals('(:1:f)(b:2:t)(c:3:f)', $testFn($root['a']));
        $this->assertEquals('(:2:t)', $testFn($root['a']['b']));

        // test flatten()
        $this->assertEquals(['' => 0, 'a' => 1, 'a/b' => 2, 'a/c' => 3], $root->flattenValues());
        $this->assertEquals(['' => 1, 'b' => 2, 'c' => 3], $root['a']->flattenValues());
        $this->assertEquals(['' => 2], $root['a']['b']->flattenValues());
    }

    public function testClone()
    {
        $root = new StdTreeNode(0, ['foo' => true]);
        $a = $root['a'] = new StdTreeNode(1, ['foo' => false]);
        $b = $root['a']['b'] = new StdTreeNode(2, ['foo' => true]);
        $c = $root['a']['c'] = new StdTreeNode(3, ['foo' => false]);

        $this->assertThrows(\Exception::class, function () use ($root) {
            clone $root;
        });

        $a2 = $a->clone();

        $this->assertNotSame($a, $a2);
        $this->assertSame(json_encode($a), json_encode($a2));
        $this->assertChildParentKey($a, $root, 'a');
        $this->assertChildParentKey($a2, null, null);

        $b2 = $a2['b'];
        $this->assertNotSame($b, $b2);
        $this->assertSame(json_encode($b), json_encode($b2));
        $this->assertChildParentKey($b, $a, 'b');
        $this->assertChildParentKey($b2, $a2, 'b');
    }
}
