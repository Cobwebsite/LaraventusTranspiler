<?php

namespace Aventus\Transpiler\Container;

use Aventus\Laraventus\Attributes\Export;
use Aventus\Laraventus\Attributes\Http\Delete;
use Aventus\Laraventus\Attributes\Http\Get;
use Aventus\Laraventus\Attributes\Http\Options;
use Aventus\Laraventus\Attributes\Http\Post;
use Aventus\Laraventus\Attributes\Http\Put;
use Aventus\Laraventus\Attributes\Override;
use Aventus\Laraventus\Attributes\Rename;
use Aventus\Laraventus\Helpers\AventusError;
use Aventus\Laraventus\Requests\AventusRequest;
use Aventus\Laraventus\Resources\AventusResource;
use Aventus\Laraventus\Resources\TextResponse;
use Aventus\Laraventus\Tools\Console;
use Aventus\Transpiler\Config\ProjectConfig;
use Aventus\Transpiler\Parser\PHPClass;
use Aventus\Transpiler\Parser\PHPClassMethod;
use Aventus\Transpiler\Parser\PHPType;
use PhpParser\Node\Scalar\String_;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class HttpRouteContainer
{
    private string $overrideTxt = "";
    public string $name = "";
    private string $route = "";
    public bool $canBeAdded = true;
    /** @var array<string, string> */
    public array $functionNeeded = [];
    /** @var string[] $httpMethods */
    private array $httpMethods = [];
    /** @var array<string, string> */
    private array $parametersUrlAndType = [];
    /** @var array<string, string> */
    private array $parametersBodyAndType = [];

    private ?string $typeBody = null;


    public function __construct(
        private PHPClassMethod $method,
        private HttpControllerContainer $parent
    ) {
        if ($method->hasAttribute(Override::class)) {
            $this->overrideTxt = "override ";
        }
        $this->name = $method->name;
        $this->loadHttpMethod($method);
        if (count($method->ctrlInfo) == 0) {
            $this->canBeAdded = false;
            return;
        }

        $this->parametersUrlAndType = $method->ctrlInfo[0]->parameters;
        $this->route = preg_replace_callback('/\{([^}]+)\}/', function ($matches) {
            return '${' . $matches[1] . '}';
        }, $method->ctrlInfo[0]->uri);
        if (!str_starts_with($this->route, "/")) {
            $this->route = "/" . $this->route;
        }

        $attr = $method->getAttribute(Rename::class);
        if ($attr != null) {
            $expr = $attr->args[0]->value;
            if ($expr instanceof String_) {
                $this->name = $expr->value;
            }
        }

        $this->loadRequest($method);
        $this->loadReturn($method);
    }

    private function loadHttpMethod(PHPClassMethod $method)
    {
        foreach ($method->ctrlInfo as $ctrlInfo) {
            foreach ($ctrlInfo->methods as $_method) {
                $methodLower = strtolower($_method);
                if (!in_array($methodLower, $this->httpMethods)) {
                    $this->httpMethods[] = $methodLower;
                }
            }
        }

        $methods = [
            "delete" => Delete::class,
            "get" => Get::class,
            "options" => Options::class,
            "post" => Post::class,
            "put" => Put::class,
        ];
        foreach ($methods as $result => $class) {
            if ($method->hasAttribute($class)) {
                if (!in_array($result, $this->httpMethods)) {
                    $this->httpMethods[] = $result;
                }
            }
        }
    }

    private function loadRequest(PHPClassMethod $method)
    {
        foreach ($method->params as $param) {
            if ($param->type->symbol instanceof PHPClass && $param->type->symbol->extends(AventusRequest::class)) {
                if ($param->type->symbol->hasAttribute(Export::class) || ProjectConfig::$config->exportHttpRouteByDefault) {
                    $this->typeBody = $this->parent->getTypeName($param->type);
                } else {
                    foreach ($param->type->symbol->properties as $prop) {
                        $this->parametersBodyAndType[$prop->name] = $this->parent->getTypeName($prop->type);
                    }
                }
            }
        }
    }
    private function loadReturn(PHPClassMethod $method)
    {
        // $method->return
    }


    public function write(): string
    {

        $result = [];
        $bodyKey = "body";
        if (count($this->parametersBodyAndType) > 0 || $this->typeBody != null) {
            $i = 0;
            while (array_key_exists($bodyKey, $this->parametersUrlAndType)) {
                $bodyKey = "body" . $i;
                $i++;
            }
            if ($this->typeBody != null) {
                $paramBody = $this->typeBody;
                if (ProjectConfig::$config->useFormData) {
                    $paramBody .= " | FormData";
                }
                $this->parametersUrlAndType[$bodyKey] = $paramBody;
            } else {
                $inlineValues =  array_map(fn($k, $v) => $k . ': ' . $v, array_keys($this->parametersBodyAndType), $this->parametersBodyAndType);
                $paramBody = "{ " . implode(", ", $inlineValues) . " }";
                if (ProjectConfig::$config->useFormData) {
                    $paramBody .= " | FormData";
                }
                $this->parametersUrlAndType[$bodyKey] = $paramBody;
            }
        }

        $params = implode(", ", array_map(fn($k, $v) => $k . ': ' . $v, array_keys($this->parametersUrlAndType), $this->parametersUrlAndType));

        $fctDesc = $this->parent->getAccessibility($this->method) . $this->overrideTxt . "async " . $this->name . "(" . $params . "): @resultType {";
        $request = "const request = new Aventus.HttpRequest(`\${this.getPrefix()}" . $this->route . "`, Aventus.HttpMethod." . strtoupper($this->httpMethods[0]) . ");";
        $body = "";
        if (count($this->parametersBodyAndType) > 0 || $this->typeBody != null) {
            $body = "request.setBody(" . $bodyKey . ");";
        }
        $returnTxt = "";
        $returnType =  $this->parent->omitType($this->method->return, AventusError::class) ?? PHPType::void();

        $typeTxt = $this->parent->getTypeName($returnType);
        if (count($returnType->unions) == 0 && $returnType->symbol instanceof PHPClass) {
            if ($returnType->symbol->extends(AventusResource::class)) {
                $fctDesc = str_replace("@resultType", "Promise<Aventus.ResultWithError<$typeTxt>>", $fctDesc);
                $returnTxt = "return await request.queryJSON<$typeTxt>(this.router);";
                $typeTxt = "";
            } else if ($returnType->symbol->is(TextResponse::class)) {
                $fctDesc = str_replace("@resultType", "Promise<Aventus.ResultWithError<string>>", $fctDesc);
                $returnTxt = "return await request.queryTxt(this.router);";
                $typeTxt = "";
            } else if (
                $returnType->symbol->is(StreamedResponse::class) || $returnType->symbol->is(BinaryFileResponse::class)
            ) {
                $fctDesc = str_replace("@resultType", "Promise<Aventus.ResultWithError<Blob>>", $fctDesc);
                $returnTxt = "return await request.queryBlob(this.router);";
                $typeTxt = "";
            } else {
                $fctDesc = str_replace("@resultType", "Promise<Aventus.ResultWithError<$typeTxt>>", $fctDesc);
                $returnTxt = "return await request.queryJSON<$typeTxt>(this.router);";
                $typeTxt = "";
            }
        } else if ($typeTxt == "void") {
            $returnTxt = "return await request.queryVoid(this.router);";
            $fctDesc = str_replace("@resultType", "Promise<Aventus.VoidWithError>", $fctDesc);
            $typeTxt = "";
        } else {
            $fctDesc = str_replace("@resultType", "Promise<Aventus.ResultWithError<$typeTxt>>", $fctDesc);
            $returnTxt = "return await request.queryJSON<$typeTxt>(this.router);";
            $typeTxt = "";
        }


        $this->parent->addTxt("@BindThis()", $result);
        $this->parent->addTxtOpen($fctDesc, $result);
        $this->parent->addTxt($request, $result);
        if (strlen($body) > 0) {
            if (in_array(strtoupper($this->httpMethods[0]), ["DELETE", "PUT"])) {
                $this->parent->addTxt("request.enableMethodSpoofing();", $result);
            }
            $this->parent->addTxt($body, $result);
        }
        if (strlen($typeTxt) > 0) {
            $this->parent->addTxt($typeTxt, $result);
        }
        if (strlen($returnTxt) > 0) {
            $this->parent->addTxt($returnTxt, $result);
        }
        $this->parent->addTxtClose("}", $result);


        return implode("\r\n", $result);
    }
}
