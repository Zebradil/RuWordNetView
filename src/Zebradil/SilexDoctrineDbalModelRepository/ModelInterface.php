<?php

declare(strict_types=1);

namespace Zebradil\SilexDoctrineDbalModelRepository;

interface ModelInterface
{
    /**
     * @param string $name
     * @param mixed  $value
     */
    public function __set($name, $value);

    /**
     * @return array
     */
    public static function getFieldsConfig(): array;

    /**
     * Возвращает список полей модели.
     *
     * @return string[]
     */
    public static function getFields(): array;

    /**
     * Передаёт данные в объект
     *
     * @param mixed[] $data
     * @param bool    $decode Если данные необходимо сначала декодировать, задать значение true.
     *                        Это может быть полезным, например, при передачи данных из БД.
     *
     * @return $this|ModelInterface
     */
    public function assign(array $data, $decode = false): self;

    /**
     * Получает «сырые» данные модели в виде ассоциативного массива.
     *
     * @param string[] $fields Список полей для фильтрации. Если список пустой, возвращаются все поля модели.
     * @param bool     $encode если данные необходимо преобразовать для помещения в хранилище, задать true
     *
     * @return \mixed[]
     */
    public function getRawData(array $fields = [], $encode = false): array;

    /**
     * @return bool
     */
    public function isExists(): bool;

    /**
     * @param bool $value
     *
     * @return $this|ModelInterface
     */
    public function setIsExists($value): self;

    /**
     * Декодирует/преобразовывает данные в нужный формат для использования приложением.
     *
     * @param mixed $data
     *
     * @return mixed
     */
    public function decodeData($data);

    /**
     * Кодирует/преобразовывает данные  в нужный формат для записи в хранилище.
     *
     * @param mixed $data
     *
     * @return mixed
     */
    public function encodeData($data);

    /**
     * Сравнивает два экземпляра модели.
     *
     * @param ModelInterface $another
     *
     * @return null|bool true — экземпляры одинаковы, false — экземпляры различны, null — невозможно определить
     */
    public function is(self $another): ?bool;
}
