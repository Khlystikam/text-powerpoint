<?php
require 'vendor/autoload.php'; // Подключение Composer Autoloader
use PhpOffice\PhpPresentation\IOFactory;

// Разрешаем доступ с вашего фронтенда
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

header('Content-Type: application/json; charset=utf-8');

// Если это preflight запрос (OPTIONS), сразу возвращаем статус 200
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit();
}


// Папки для загрузки и сохранения файлов
$uploadDirectory = "";
$outputDir = "";
$zipFile = ""; // Абсолютный путь к архиву


function convertPptToPptx($pptFilePath, $outputDir) {
    $pptxFilePath = $outputDir . pathinfo($pptFilePath, PATHINFO_FILENAME) . '.pptx';

    // Проверяем наличие LibreOffice
    $command = "libreoffice --version";
    $result = shell_exec($command);
    if (!$result) {
        throw new Exception('LibreOffice не установлен.');
    }

    // Выполняем конвертацию
    $command = "libreoffice --headless --convert-to pptx --outdir " . escapeshellarg($outputDir) . " " . escapeshellarg($pptFilePath);
    $output = shell_exec($command);

    // Логирование
    error_log("Команда: $command");
    error_log("Вывод LibreOffice: $output");

    // Проверяем успешность конвертации
    if (!file_exists($pptxFilePath)) {
        error_log("Файл $pptxFilePath не был создан.");
        throw new Exception("Не удалось конвертировать $pptFilePath в PPTX.");
    }

    return $pptxFilePath;
}


// Создаем папки, если они не существуют
if (!is_dir($uploadDirectory)) {
    mkdir($uploadDirectory, 0777, true);
}
if (!is_dir($outputDir)) {
    mkdir($outputDir, 0777, true);
}

// Проверяем, были ли файлы отправлены
if (isset($_FILES['files'])) {
    $files = $_FILES['files']; // Получаем массив с файлами

    // Удаляем старый архив, если он существует
    if (file_exists($zipFile)) {
        unlink($zipFile); // Удаляем файл архива
    }

    // Создаем новый ZIP архив
    $zip = new ZipArchive();
    if ($zip->open($zipFile, ZipArchive::CREATE) !== TRUE) {
        echo json_encode(['error' => 'Не удалось открыть архив для записи.']);
        exit();
    }

    // Проходим по каждому файлу
    foreach ($files['name'] as $key => $fileName) {
        // Проверяем на наличие ошибок при загрузке
        if ($files['error'][$key] === UPLOAD_ERR_OK) {
            $tmpName = $files['tmp_name'][$key]; // Временный файл

            // Определяем расширение файла
            $fileExtension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

            // Если файл в формате .ppt, конвертируем его в .pptx
            if ($fileExtension === 'ppt') {
                try {
                    // Указываем папку для сохранения конвертированного файла
                    $convertedFilePath = convertPptToPptx($tmpName, $uploadDirectory); // Функция для конвертации
                    
                    // Проверяем, создался ли конвертированный файл
                    if (!file_exists($convertedFilePath)) {
                        throw new Exception("Конвертированный файл $convertedFilePath не найден.");
                    }

                    // Обновляем временный путь файла на конвертированный файл
                    $tmpName = $convertedFilePath;
                    $fileName = basename($convertedFilePath);
                    error_log("Файл $fileName успешно конвертирован в .pptx.");
                } catch (Exception $e) {
                    error_log("Ошибка конвертации файла $fileName: " . $e->getMessage());
                    echo json_encode(['error' => "Ошибка конвертации файла $fileName: " . $e->getMessage()]);
                    exit();
                }
            }

            // Генерируем имя для текстового файла
            $txtFileName = pathinfo($fileName, PATHINFO_FILENAME) . ".txt";
            $txtFilePath = $outputDir . $txtFileName;

            try {
                // Загружаем PPTX файл
                $pptReader = IOFactory::createReader('PowerPoint2007');
                $presentation = $pptReader->load($tmpName);

                // Извлекаем текст из каждого слайда
                $textContent = "";
                $slideIndex = 1; // Счётчик слайдов
                foreach ($presentation->getAllSlides() as $slide) {
                    $slideText = "Слайд $slideIndex:" . PHP_EOL; // Добавляем заголовок для слайда
                    foreach ($slide->getShapeCollection() as $shape) {
                        try {
                            // Проверяем, является ли shape текстовым
                            if ($shape instanceof \PhpOffice\PhpPresentation\Shape\RichText) {
                                $plainText = $shape->getPlainText();
                                // Фильтруем пустые или некорректные текстовые данные
                                if (!empty($plainText)) {
                                    $slideText .= $plainText . PHP_EOL;
                                }
                            }
                        } catch (\Exception $e) {
                            // Логируем ошибку, но продолжаем обработку
                            error_log("Ошибка при обработке слайда: " . $e->getMessage());
                            continue;
                        }
                    }
                    $textContent .= $slideText . PHP_EOL; // Добавляем перенос строки между слайдами
                    $slideIndex++;
                }

                // Записываем текст в .txt файл
                file_put_contents($txtFilePath, $textContent);

                // Добавляем .txt файл в ZIP архив
                $zip->addFile($txtFilePath, $txtFileName);
            } catch (Exception $e) {
                echo json_encode(['error' => "Ошибка обработки файла $fileName: " . $e->getMessage()]);
                exit();
            }
        }
    }

    // Закрываем архив
    $zip->close();

    // Удаляем все файлы в папке uploads
    deleteFilesInDirectory($uploadDirectory);

    // Удаляем все файлы в папке output
    deleteFilesInDirectory($outputDir);

    // Возвращаем ссылку на архив
    echo json_encode(['downloadUrl' => "ваш путь к архиву $zipFile"]);
} else {
    echo json_encode(['error' => 'Файлы не были загружены.']);
}

// Функция для удаления только файлов в директории, но не самой директории
function deleteFilesInDirectory($dir) {
    if (is_dir($dir)) {
        $files = array_diff(scandir($dir), array('.', '..'));
        foreach ($files as $file) {
            $filePath = $dir . DIRECTORY_SEPARATOR . $file;
            if (is_dir($filePath)) {
                deleteFilesInDirectory($filePath);
                rmdir($filePath); // Удаляем вложенные папки
            } else {
                unlink($filePath); // Удаляем файл
            }
        }
    }
}
?>
