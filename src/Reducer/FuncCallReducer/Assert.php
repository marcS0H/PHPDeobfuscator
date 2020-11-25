<?php
namespace Reducer\FuncCallReducer;

use PhpParser\Node\Expr\FuncCall;
use Reducer\EvalReducer;
use ValRef\ScalarValue;
use Utils;

class Assert implements FunctionReducer
{
    private $evalReducer;

    public function __construct(EvalReducer $evalReducer) {
        $this->evalReducer = $evalReducer;
    }
    public function getSupportedNames()
    {
        return ['assert'];
    }

    private function tryRunAssert($code)
    {
        try {
            // Assert basically behaves like eval when fed with a string.
            return $this->evalReducer->runEval($code);
        } catch (\Exception $e) {
            print "Error traversing". PHP_EOL;
            echo $e->getMessage() . PHP_EOL;
            echo $e->getTraceAsString() . PHP_EOL;
            return null;
        }
    }

    public function execute($name, array $args, FuncCall $node)
    {
        if(count($args) == 0) {
            return null;
        }

        $value = $args[0]->getValue();

        if(!is_string($value)) {
            return null;
        }

        return $this->tryRunAssert($value);
    }
}
