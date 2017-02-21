<?php

use ModelGenerator\ModelGenerator;

require 'modelGenerator.php';

if (!empty($_GET['tab'])) {
    echo new ModelGenerator($_GET['tab'], !empty($_GET['class']) ? $_GET['class'] : null);
} else {
    echo 'Передайте имя таблицы через GET параметр tab ';
}
