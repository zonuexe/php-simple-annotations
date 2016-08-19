<?php

namespace DocBlockReader;

class Reader
{
    const keyPattern = '[A-z0-9\_\-]+';
    const endPattern = "[ ]*(?:@|\r\n|\n)";

    /** @var string */
    private $rawDocBlock;

    /** @var array */
    private $parameters = [];

    /** @var bool */
    private $parsedAll = false;

    /**
     * @param string $doc_block
     */
    public function __construct($doc_block)
    {
        $this->rawDocBlock = $doc_block;
    }

    /**
     * Reset the doc block content so the object is reusable
     *
     * @param string $doc_block
     */
    public function setDocBlockContent($doc_block)
    {
        $this->parameters = [];
        $this->parsedAll = false;

        $this->rawDocBlock = $doc_block;
    }

    /**
     * @param  \Reflector|string|object $class_or_reflector
     * @param  string $method
     * @return Reader
     */
    public static function read($class_or_reflector, $method = null)
    {
        // get reflection from class or class/method
        // (depends on constructor arguments)
        if (empty($class_or_reflector)) {
            throw new \DomainException('No zero argument constructor allowed');
        }

        if ($method === null) {
            if ($class_or_reflector instanceof \Reflector) {
                $reflection = $class_or_reflector;
            } else {
                $reflection = new \ReflectionClass($class_or_reflector);
            }
        } else {
            $reflection = new \ReflectionMethod($class_or_reflector, $method);
        }

        return new Reader($reflection->getDocComment());
    }

    /**
     * @param  string $key
     * @return mixed
     */
    public function parseSingle($key)
    {
        if (isset($this->parameters[$key])) {
            return $this->parameters[$key];
        }

        if (preg_match('/@'.preg_quote($key).self::endPattern.'/', $this->rawDocBlock, $match)) {
            return true;
        }
        preg_match_all('/@'.preg_quote($key).' (.*)'.self::endPattern.'/U', $this->rawDocBlock, $matches);
        $size = count($matches[1]);

        // not found
        if ($size === 0) {
            return null;
        }
        // found one, save as scalar
        elseif ($size === 1) {
            return $this->parseValue($matches[1][0]);
        }

        // found many, save as array
        $this->parameters[$key] = [];
        foreach ($matches[1] as $elem) {
            $this->parameters[$key][] = $this->parseValue($elem);
        }

        return $this->parameters[$key];
    }

    public function parse()
    {
        $pattern = "/@(?=(.*)".self::endPattern.")/U";

        preg_match_all($pattern, $this->rawDocBlock, $matches);

        foreach($matches[1] as $rawParameter) {
            if(preg_match('/^('.self::keyPattern.') (.*)$/', $rawParameter, $match)) {
                if(isset($this->parameters[$match[1]])) {
                    $this->parameters[$match[1]] = array_merge((array)$this->parameters[$match[1]], (array)$match[2]);
                } else {
                    $this->parameters[$match[1]] = $this->parseValue($match[2]);
                }
            } elseif(preg_match('/^'.self::keyPattern.'$/', $rawParameter, $match)) {
                $this->parameters[$rawParameter] = true;
            } else {
                $this->parameters[$rawParameter] = null;
            }
        }
    }

    /**
     * @param string $name
     */
    public function getVariableDeclarations($name)
    {
        $declarations = (array)$this->getParameter($name);

        foreach($declarations as $i => $declaration) {
            $declarations[$i] = $this->parseVariableDeclaration($declaration, $name);
        }

        return $declarations;
    }

    public function parseVariableDeclaration($declaration, $name)
    {
        if (!is_string($declaration)) {
            $type = gettype($declaration);
            $message = "Raw declaration must be string, $type given. Key='$name'.";
            throw new \InvalidArgumentException($message);
        }

        if (strlen($declaration) === 0) {
            $message = "Raw declaration cannot have zero length. Key='$name'.";
            throw new \InvalidArgumentException($message);
        }

        $declaration = explode(' ', $declaration);
        if(count($declaration) === 1) {
            // string is default type
            array_unshift($declaration, 'string');
        }

        // take first two as type and name
        $declaration = [
            'type' => $declaration[0],
            'name' => $declaration[1],
        ];

        return $declaration;
    }

    /**
     * @param  string $originalValue
     * @return mixed
     */
    public function parseValue($originalValue)
    {
        if($originalValue && $originalValue !== 'null') {
            // try to json decode, if cannot then store as string
            $json = json_decode($originalValue, true);
            $value = ($json === null) ? $originalValue : $json;
        } else {
            $value = null;
        }

        return $value;
    }

    /**
     * @return array
     */
    public function getParameters()
    {
        if (!$this->parsedAll) {
            $this->parse();
            $this->parsedAll = true;
        }

        return $this->parameters;
    }

    /**
     * @param string $key
     */
    public function getParameter($key)
    {
        return $this->parseSingle($key);
    }
}
