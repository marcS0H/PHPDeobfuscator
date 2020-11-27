<?php

use PhpParser\Node;

class RuleMatchingVisitor extends PhpParser\NodeVisitorAbstract
{
    private $deobf = '';
    private $declaredSuspicious = false;
    // Nodes that _may_ indicate the script is dealing with files
    // Or doing buffer maths that is often found in malware.
    private $fileHandlingIndicators = [];

    public function __construct(Deobfuscator $deobf)
    {
        $this->deobf = $deobf;
    }
    public function enterNode(Node $node)
    {
        $this->findFileHandlingIndicators($node);
    }

    public function afterTraverse(array $nodes) {
        if( $this->fileHandlingIndicators && !$this->declaredSuspicious ) {
            $this->declaredSuspicious = true;
            print_r("{$this->deobf->getCurrentFileName()} looks like a malware!\n");
        }
    }

    public function findFileHandlingIndicators(Node $node) 
    {
        if($node instanceof Node\Scalar\LNumber) {
            // 1024 is a multiple that is often used when dealing disk space and file sizes 
            // May indicate we're dealing with files or some kind of buffering.
            if($node->value > 0 && $node->value % 1024 == 0) {
                $this->fileHandlingIndicators[] = $node;
            }
        }
    }
}
