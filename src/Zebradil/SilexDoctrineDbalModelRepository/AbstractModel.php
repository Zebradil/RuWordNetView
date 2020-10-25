<?php

namespace Zebradil\SilexDoctrineDbalModelRepository;

use Doctrine\DBAL\Types\Type;

/**
 * Class AbstractModel.
 */
abstract class AbstractModel implements ModelInterface
{
    const FIELDS_CONFIG = [];
    protected static $_fields_defaults = [];
    protected $_isExists = false;
    protected $_loadAfterSave = false;
    /**
     * @var string
     */
    protected $protoClassName = '';
    /**
     * @var RepositoryFactoryService
     */
    protected $_repositoryFactory;

    /**
     * {@inheritdoc}
     */
    public function __construct(RepositoryFactoryService $repositoryFactory)
    {
        if (empty(static::FIELDS_CONFIG)) {
            throw new \UnexpectedValueException('Field configuration not defined in class '.static::class);
        }

        $this->_repositoryFactory = $repositoryFactory;
        $defaults = self::getDefaultValues();
        if ($defaults) {
            $this->assign($defaults);
        }
    }

    public function __isset($name): bool
    {
        $cfg = static::FIELDS_CONFIG;

        return isset($cfg[$name]);
    }

    /** {@inheritdoc} */
    public function __set($name, $value)
    {
        $cfg = static::FIELDS_CONFIG;
        if (!isset($cfg[$name])) {
            throw new \InvalidArgumentException("Попытка присвоения значения непубличному свойству «{$name}»");
        }
        $this->{$name} = $this->cast($name, $value);
    }

    /** {@inheritdoc} */
    public function assign(array $data, $decode = false)
    {
        $data = $decode ? $this->decodeData($data) : $data;
        $cfg = static::FIELDS_CONFIG;
        foreach ($data as $k => $v) {
            if (isset($cfg[$k])) {
                $this->{$k} = $this->cast($k, $v, !$decode);
            }
        }

        return $this;
    }

    /** {@inheritdoc} */
    public function decodeData($data)
    {
        return $data;
    }

    /**
     * @param $fields
     *
     * @return array
     */
    public static function filterPublicFields($fields): array
    {
        $modelFields = static::FIELDS_CONFIG;

        return array_filter($fields, function ($field) use ($modelFields) {
            return isset($modelFields[$field]) && !empty($modelFields[$field]['public']);
        });
    }

    /**
     * @return array
     */
    public static function getFieldsConfig(): array
    {
        return static::FIELDS_CONFIG;
    }

    /**
     * Возвращает свойства объекта в виде ассоциативного массива.
     * Добавлен для совместимости с MyObj. Будет удалён в дальнейшем.
     *
     * @param array $fields
     *
     * @return mixed[]
     *
     * @deprecated
     */
    public function getAttributes(array $fields = []): array
    {
        return $this->getRawData($fields);
    }

    /** {@inheritdoc} */
    public function getRawData(array $fields = [], $encode = false): array
    {
        $data = [];
        $modelFields = static::getFields();
        $fields = $fields ? array_intersect($modelFields, $fields) : $modelFields;
        foreach ($fields as $field) {
            $data[$field] = $this->{$field};
        }

        return $encode ? $this->encodeData($data) : $data;
    }

    /** {@inheritdoc} */
    public static function getFields(): array
    {
        return array_keys(static::FIELDS_CONFIG);
    }

    /** {@inheritdoc} */
    public function encodeData($data)
    {
        return $data;
    }

    /** {@inheritdoc} */
    public function setIsExists($value)
    {
        $this->_isExists = (bool) $value;

        return $this;
    }

    /**
     * Проверяет, загружена ли модель. Выбрасывает исключение, если нет.
     */
    public function ensureLoaded()
    {
        if (!$this->isExists()) {
            throw new \LogicException('Объект не загружен.');
        }
    }

    /** {@inheritdoc} */
    public function isExists(): bool
    {
        return $this->_isExists;
    }

    public function is(ModelInterface $another): ?bool
    {
        $static = static::class;
        if ($another instanceof $static) {
            if (!$this->isExists() || !$another->isExists()) {
                return null;
            }

            return false;
        }

        throw new \UnexpectedValueException('Instance of '.static::class.' class expected, got instance of '.\get_class($another));
    }

    /**
     * @return array
     */
    protected static function getDefaultValues(): array
    {
        $defaults = [];
        foreach (static::FIELDS_CONFIG as $field => $cfg) {
            if (isset($cfg['default'])) {
                $defaults[$field] = $cfg['default'];
            }
        }

        return $defaults;
    }

    /**
     * Преобразует значение к указанному типу поля модели.
     *
     * @param string $name   Имя поля модели
     * @param mixed  $value  Преобразуемое значение
     * @param bool   $encode Применять ли обработку значения с помощью указанного в конфиге метода
     *
     * @return bool|int|mixed|string
     */
    private function cast($name, $value, $encode = true)
    {
        $cfg = static::FIELDS_CONFIG[$name];
        if (\is_array($cfg)) {
            if ($encode && isset($cfg['process_fn'])) {
                $value = $this->{$cfg['process_fn']}($value, $name, $cfg);
            }
            if (!empty($cfg['nullable'])) {
                if (null === $value) {
                    return null;
                }
                if ('' === $value && Type::STRING !== $cfg['type']) {
                    return null;
                }
            }
            switch ($cfg['type']) {
                case Type::BOOLEAN:
                    return (bool) $value;
                case Type::INTEGER:
                    return (int) $value;
                case Type::STRING:
                    return (string) $value;
                default:
                    return $value;
            }
        } else {
            return $value;
        }
    }
}
