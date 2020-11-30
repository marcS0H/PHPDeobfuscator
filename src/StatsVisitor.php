<?php
use PhpParser\{NodeVisitorAbstract, Node};
use PhpParser\PrettyPrinter;

class StatsVisitor extends NodeVisitorAbstract {
    // Function Call Statistics
    private $funcCallStats;
    // Variable Name Stats
    private $varNameStats;
    // Set to true if we ran into some PHP code at all
    private $hasVisitedNodes;
    // Current deobfuscator instance
    private $deobf;

    public function __construct(Deobfuscator $deobf) {
        $this->hasVisitedNodes = false;
        $this->funcCallStats = [];
        $this->varNameStats = [];
        $this->deobf = $deobf;
    }

    public function enterNode(Node $node) {
        if( ! $node instanceof Node\Stmt\InlineHTML ) {
            $this->hasVisitedNodes = true;
        }

        if( $this->isVariableInstance($node) ) {
            $index = $this->getPhpCode($node);
            if( isset($this->varNameStats[$index]) ) {
                $this->varNameStats[$index]++;
            }
            else {
                $this->varNameStats[$index] = 1;
            }
        }

        if( $node instanceof Node\Expr\FuncCall ) {
            // The function called may be called dynamically, like `$name('parameter')`
            // which can't be cast to a string and thus, don't make good array indexes.
            // Let's convert the name back into code (string) to make sure we can use it as such.
            if( $node->name instanceof Node\Name || $this->isVariableInstance($node->name) ) {
                $index = $this->getPhpCode($node->name);
                if( isset($this->funcCallStats[$index]) ) {
                    $this->funcCallStats[$index]++;
                }
                else {
                    $this->funcCallStats[$index] = 1;
                }
            }
        }
    }
    
    // Get generated PHP code of a node without "<?php" or ";"
    private function getPhpCode(Node $node) {
        $generated_code = (new PrettyPrinter\Standard())->prettyPrintFile([$node]);
        $generated_code = str_replace("<?php\n\n", '', $generated_code);
        return rtrim($generated_code, ';');
    }

    private function isVariableInstance(Node $node) {
        if( 
            $node instanceof Node\Expr\Variable ||
            $node instanceof Node\Expr\PropertyFetch ||
            $node instanceof Node\Expr\ArrayDimFetch
        ) {
            return true;
        }
        return false;
    }
    // Using the __destruct magic method to ensure this is called only once, when the process stops.
    // For context, a new traversal is launched for every eval'ed statements. This is a problem
    // if you rely on NodeVisitor's afterTraverse method
    public function __destruct() {
        echo json_encode($this->varNameStats) . PHP_EOL;
    }
}
