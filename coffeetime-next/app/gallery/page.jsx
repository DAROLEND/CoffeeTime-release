import Image from "next/image";

const PHOTOS = Array.from({ length: 10 }, (_, i) => ({
  id: i + 1,
  src: `/images/gallery/photo${i + 1}.png`,
  alt: `Фото галереї ${i + 1}`,
}));

export const metadata = {
  title: "Галерея — Coffee Time",
  description: "Фотогалерея кав'ярні Coffee Time",
};

export default function GalleryPage() {
  return (
    <div className="max-w-site mx-auto px-4 py-10">
      <h1 className="text-3xl font-bold text-center text-dark mb-2">
        Галерея
      </h1>
      <p className="text-center text-gray-500 text-sm mb-10">
        Атмосфера Coffee Time у фотографіях
      </p>

      {/*
        Masonry-style layout using CSS columns.
        Tailwind's `columns-*` utilities map directly to column-count.
      */}
      <div className="columns-1 sm:columns-2 lg:columns-3 gap-4 space-y-4">
        {PHOTOS.map((photo) => (
          <div
            key={photo.id}
            className="break-inside-avoid overflow-hidden rounded-2xl shadow-md
              hover:shadow-xl transition-all duration-300 group"
          >
            <div className="relative w-full overflow-hidden">
              <Image
                src={photo.src}
                alt={photo.alt}
                width={600}
                height={400}
                className="w-full h-auto object-cover
                  transition-transform duration-500 group-hover:scale-105"
                sizes="(max-width: 640px) 100vw, (max-width: 1024px) 50vw, 33vw"
              />
            </div>
          </div>
        ))}
      </div>
    </div>
  );
}
