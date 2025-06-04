import React, { useState } from 'react';
import './mainTextPowerpoint.css';
import './style/table-max-768.css';
import './style/mobile-max-425.css';


const MainTextPowerpoint: React.FC = () => {
    const [files, setFiles] = useState<File[]>([]);
    const [downloadUrl, setDownloadUrl] = useState<string | null>(null);
    const [isLoading, setIsLoading] = useState<boolean>(false);
    const [errorMessage, setErrorMessage] = useState<string | null>(null);

    const handleFileInput = (e: React.ChangeEvent<HTMLInputElement>) => {
        if (e.target.files) {
            setFiles(Array.from(e.target.files)); // Преобразуем список файлов в массив
        }
    };

    const handleFormSubmit = async (e: React.FormEvent<HTMLFormElement>) => {
        e.preventDefault();
        setIsLoading(true);
        setErrorMessage(null); // Очистить предыдущие ошибки

        const formData = new FormData();
        files.forEach(file => {
            formData.append('files[]', file);
        });

        try {
            const response = await fetch('https://dev-magick-api.ru/php/pptx-txt/pptx-txt.php', {
                method: 'POST',
                body: formData,
            });

            const data = await response.json(); // Обработка JSON-ответа

            if (data.error) {
                console.error('Ошибка:', data.error);
                setErrorMessage(data.error); // Показываем ошибку
            } else if (data.downloadUrl) {
                setDownloadUrl(data.downloadUrl); // Сохраняем ссылку для скачивания
            }
        } catch (error) {
            console.error('Ошибка сети:', error);
            setErrorMessage('Произошла ошибка при загрузке файлов. Попробуйте снова.'); // Сообщение об ошибке сети
        } finally {
            setIsLoading(false);
        }
    };

    const handleDownload = () => {
        if (downloadUrl) {
            // Открытие ссылки в новом окне браузера
            window.open(downloadUrl, '_blank');
        }
    };

    // Ограничение на размер файла
    const MAX_FILE_SIZE = 10 * 1024 * 1024; // 10MB

    const handleFileValidation = (file: File) => {
        if (file.size > MAX_FILE_SIZE) {
            return `Файл ${file.name} слишком большой. Максимальный размер — 10 МБ.`;
        }
        if (!file.name.endsWith('.ppt') && !file.name.endsWith('.pptx')) {
            return `Файл ${file.name} не является презентацией PowerPoint.`;
        }
        return null;
    };

    return (
        <form
            onSubmit={handleFormSubmit}
            encType="multipart/form-data"
        >
            <div className="main-text-powerpoint-container">
                <h2>Здесь можно извлечь текст из презентаций PowerPoint и сохранить его в текстовый файл.</h2>

                <div className="main-text-powerpoint-dropzone-container">
                    <p className="dropzone-container-p">Перетащите файлы сюда или нажмите, чтобы выбрать файлы</p>
                    <input
                        className="main-text-powerpoint-dropzone"
                        type="file"
                        name="pptx_files[]"
                        accept=".pptx, .ppt"
                        onChange={handleFileInput}
                        id="fileInput"
                        placeholder="Перетащите файлы сюда или нажмите, чтобы выбрать файлы."
                        multiple
                    />
                    {files.length > 0 && (
                        files.length < 14 ? (
                            <ul className="dropzone-container-name-file">
                                {files.map((file, index) => {
                                    const fileError = handleFileValidation(file);
                                    return (
                                        <li key={index} className="dropzone-container-p-name-file">
                                            {fileError ? (
                                                <span className="error-text">{fileError}</span>
                                            ) : (
                                                `Выбран файл: ${file.name}`
                                            )}
                                        </li>
                                    );
                                })}
                            </ul>
                        ) : (
                            <p className="dropzone-container-p-name-file-count">
                                Выбрано количество файлов: {files.length}
                            </p>
                        )
                    )}
                </div>

                {errorMessage && (
                    <p className="error-message">{errorMessage}</p>
                )}

                <button
                    className="main-text-powerpoint-button"
                    type="submit"
                    disabled={isLoading || files.some(file => handleFileValidation(file))}
                >
                    {isLoading ? 'Обрабатываем файлы...' : 'Загрузить файлы'}
                </button>

                {downloadUrl && (
                    <button
                        className="main-text-powerpoint-button"
                        type="button"
                        onClick={handleDownload}
                    >
                        Скачать txt файлы
                    </button>
                )}
            </div>
        </form>
    );
};

export default MainTextPowerpoint;
