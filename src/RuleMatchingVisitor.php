<?php

use PhpParser\Node;
use PhpParser\PrettyPrinter;
/**
 * The RuleMatchingVisitor class is used to find potentially malicious PHP scripts by
 * finding patterns and attributes commonly found in malware. 
 * 
 * If the script uses an integer that is a multiple of 1024 somewhere in the code and 
 * it doesn't show common things found in professional PHP code, we will flag it for
 * further manual analysis.    
 */
class RuleMatchingVisitor extends PhpParser\NodeVisitorAbstract
{
    // The Deobfuscator object.
    private $deobf = null;
    // Set to `true` if the script is declaring using a namespace.
    private $usesNamespaces = false;
    // See if the code contains any PHPDoc-style comment (e.g. @package,@param, etc.).
    private $usesPhpDocComments = false;
    // Nodes that _may_ indicate the script is dealing with memory-related operations
    // or doing buffer maths that is often found in file-handling scripts.
    private $memoryHandlingEvidence = [];
    // Nodes that may be executing shell commands or arbitrary functions.
    private $dangerousFunctionsEvidence = [];

    public function __construct(Deobfuscator $deobf)
    {
        $this->deobf = $deobf;
    }

    public function enterNode(Node $node)
    {
        $this->containsNamespaceDefinition($node);
        $this->containsPhpDocComments($node);
        $this->findMemoryHandlingEvidence($node);
        $this->findDangerousFunctionsEvidence($node);
    }

    public function __destruct() 
    {
        if(
            !$this->usesNamespaces &&
            !$this->usesPhpDocComments &&
            (
                $this->memoryHandlingEvidence ||
                $this->dangerousFunctionsEvidence
            )
        ){
            print_r("{$this->deobf->getCurrentFileName()} looks like a malware!\n");
        }
    }

    public function containsNamespaceDefinition(Node $node)
    {
        if($node instanceof Node\Stmt\Namespace_) {
            $this->usesNamespaces = true;
        }
    }

    public function containsPhpDocComments(Node $node)
    {
        $comments = $node->getAttribute('comments');

        // No need to continue if this node doesn't have any comments at all.
        if(!$comments) {
            return;
        }
        // 'comments' is always an array
        foreach($comments as $comment) {
            $this->usesPhpDocComments |= (bool)preg_match(
                '/@(?:class|return|param|category|(?:sub)?package|author|copyright|license|link|since|var)\s/', 
                $comment->getReformattedText()
            );
        }
    }

    public function findMemoryHandlingEvidence(Node $node)
    {
        if($node instanceof Node\Scalar\LNumber) {
            // 1024 is a multiple that is often used when dealing disk space and file sizes 
            // May indicate we're dealing with files or some kind of buffering.
            if($node->value > 0 && $node->value % 1024 == 0) {
                // Make sure this integer is not used as an index in some array defitions
                if( ! $node->getAttribute('parent') instanceof Node\Expr\ArrayItem ) {
                    $this->memoryHandlingEvidence[] = $node;
                }
            }

        }
    }
    public function findDangerousFunctionsEvidence(Node $node) 
    {
        if($node instanceof Node\Expr\FuncCall) {
            if(in_array($node->name, ['passthru', 'system'])) {
                $this->dangerousFunctionsEvidence[] = $node;
            }
            elseif(is_object($node->name) && $node->name instanceof Node\Expr\Variable) {
                $this->dangerousFunctionsEvidence[] = $node;
            }
        }
        elseif($node instanceof Node\Expr\ShellExec) {
            $this->dangerousFunctionsEvidence[] = $node;
        }
    }
}