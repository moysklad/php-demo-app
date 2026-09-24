import { useState } from "react";
import { Button, ButtonVariants } from "@moysklad/uikit/components/Button";
import { Carousel } from "@moysklad/uikit/components/Carousel";
import { FileUploader, useFileUploader } from "@moysklad/uikit/components/FileUploader";
import { Text } from "@moysklad/uikit/components/Text";
import { VStack } from "@moysklad/uikit/components/VStack";
import { Section } from "../Section";

const SNIPPET = `
import { Carousel } from "@moysklad/uikit/components/Carousel";
import { FileUploader, useFileUploader } from "@moysklad/uikit/components/FileUploader";

const uploader = useFileUploader({ accept: { "image/*": [] } });

<FileUploader.Trigger {...uploader.getInputProps()}>
  <Button variant={ButtonVariants.ADDITIONAL} onClick={uploader.open}>Загрузить изображения</Button>
</FileUploader.Trigger>
<FileUploader files={uploader.files} invalidFiles={uploader.invalidFiles} onDeleteFile={uploader.removeFile} />

<Carousel sources={sources} isVisible={isGalleryOpen} onClose={() => setGalleryOpen(false)} />
`;

/* Демо-картинки для галереи: инлайновые SVG, чтобы пример работал без внешних ресурсов. */
const GALLERY = ["#036CE5", "#00B85C", "#FF9500"].map(
  (color, index) =>
    `data:image/svg+xml,${encodeURIComponent(
      `<svg xmlns="http://www.w3.org/2000/svg" width="640" height="420"><rect width="100%" height="100%" fill="${color}"/><text x="50%" y="50%" fill="#fff" font-family="sans-serif" font-size="48" text-anchor="middle" dominant-baseline="middle">Фото ${index + 1}</text></svg>`
    )}`
);

/** Изображения: загрузка файлов с превью и полноэкранная галерея. */
export function ImagesSection() {
  const [isGalleryOpen, setGalleryOpen] = useState(false);
  const uploader = useFileUploader({ accept: { "image/*": [] } });

  return (
    <Section
      title="Изображения"
      description="FileUploader — загрузка файлов с устройства: превью, валидация, удаление. Carousel — полноэкранная галерея с листанием и масштабом; в iframe она ограничена его рамкой."
      file="ImagesSection.tsx"
      snippet={SNIPPET}
    >
      <VStack size="s12">
        <div>
          <FileUploader.Trigger {...uploader.getInputProps()}>
            <Button variant={ButtonVariants.ADDITIONAL} onClick={uploader.open}>
              Загрузить изображения
            </Button>
          </FileUploader.Trigger>
        </div>
        {uploader.files.length > 0 ? (
          <FileUploader files={uploader.files} invalidFiles={uploader.invalidFiles} onDeleteFile={uploader.removeFile} />
        ) : (
          <Text.Caption>Файлы никуда не отправляются: пример живет в памяти страницы.</Text.Caption>
        )}
        <div>
          <Button variant={ButtonVariants.ADDITIONAL} onClick={() => setGalleryOpen(true)}>
            Открыть галерею
          </Button>
        </div>
      </VStack>
      <Carousel sources={GALLERY} isVisible={isGalleryOpen} onClose={() => setGalleryOpen(false)} />
    </Section>
  );
}
