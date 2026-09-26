<?php

use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Manual de uso')] class extends Component {
    /**
     * Lee el manual en Markdown, lo convierte a HTML, agrega anclas a los
     * títulos y arma el índice.
     *
     * @return array{html:string, toc:list<array{id:string, title:string, level:int}>}
     */
    #[Computed]
    public function manual(): array
    {
        $path = resource_path('manual/manual.md');
        $markdown = File::exists($path) ? File::get($path) : '# Manual no disponible';

        $toc = [];

        $html = preg_replace_callback('~<h([23])>(.*?)</h\1>~s', function (array $matches) use (&$toc): string {
            $title = trim(strip_tags($matches[2]));
            $id = Str::slug($title) ?: 'seccion';

            $toc[] = ['id' => $id, 'title' => $title, 'level' => (int) $matches[1]];

            return "<h{$matches[1]} id=\"{$id}\">{$matches[2]}</h{$matches[1]}>";
        }, Str::markdown($markdown));

        // Las imágenes del manual viven en /public/manual.
        $html = str_replace('src="manual/', 'src="'.asset('manual').'/', $html);

        return ['html' => $html, 'toc' => $toc];
    }
}; ?>

@php($manual = $this->manual)

<div class="mx-auto flex w-full max-w-6xl flex-col gap-6 py-4" x-data="{ q: '' }">
    <header>
        <p class="text-sm font-medium text-[#7F5C12]">Ayuda</p>
        <h1 class="font-display mt-1 text-3xl font-semibold text-[#17150F]">Manual de uso</h1>
        <p class="mt-2 max-w-3xl text-[#5F584A]">Guía del sistema. Use el buscador o el índice para ir a una sección.</p>
    </header>

    <div class="grid gap-6 lg:grid-cols-[260px_minmax(0,1fr)]">
        <aside class="min-w-0 lg:sticky lg:top-20 lg:self-start">
            <div class="rounded-xl border border-[#E3DED3] bg-white p-4 shadow-sm">
                <flux:input x-model="q" icon="magnifying-glass" placeholder="Buscar en el manual…" size="sm" />

                <nav class="mt-3 max-h-[70vh] space-y-0.5 overflow-y-auto">
                    @foreach ($manual['toc'] as $item)
                        <a href="#{{ $item['id'] }}"
                            x-show="q === '' || @js(Str::lower($item['title'])).includes(q.toLowerCase())"
                            @class([
                                'block rounded-lg px-3 py-1.5 text-[#5F584A] hover:bg-[#F3F1EC] hover:text-[#17150F]',
                                'ps-6 text-[13px]' => $item['level'] === 3,
                                'text-sm font-semibold' => $item['level'] === 2,
                            ])>
                            {{ $item['title'] }}
                        </a>
                    @endforeach
                </nav>
            </div>
        </aside>

        <article class="manual min-w-0 rounded-xl border border-[#E3DED3] bg-white p-6 shadow-sm sm:p-8">
            {!! $manual['html'] !!}
        </article>
    </div>
</div>

<style>
    .manual { color: #3D382E; line-height: 1.7; }
    .manual > h1 { display: none; }
    .manual h2 { margin: 2rem 0 .75rem; padding-bottom: .5rem; border-bottom: 1px solid #EDE9E0; font-size: 1.25rem; font-weight: 700; color: #17150F; scroll-margin-top: 5rem; }
    .manual h2:first-of-type { margin-top: 0; }
    .manual h3 { margin: 1.25rem 0 .5rem; font-size: 1.05rem; font-weight: 600; color: #17150F; scroll-margin-top: 5rem; }
    .manual p { margin: .75rem 0; }
    .manual ul, .manual ol { margin: .75rem 0; padding-left: 1.25rem; }
    .manual ul { list-style: disc; }
    .manual ol { list-style: decimal; }
    .manual li { margin: .35rem 0; }
    .manual strong { color: #17150F; font-weight: 600; }
    .manual a { color: #7F5C12; text-decoration: underline; }
    .manual code { border-radius: .375rem; background: #F3F1EC; padding: .1rem .35rem; font-size: .85em; }
    .manual blockquote { margin: 1rem 0; border-left: 3px solid #C9A043; border-radius: .5rem; background: #F6EEDB; padding: .75rem 1rem; color: #5F584A; }
    .manual blockquote p { margin: 0; }
    .manual img { display: block; width: 100%; margin: 1rem 0; border: 1px solid #E3DED3; border-radius: .75rem; }
    .manual hr { margin: 2rem 0; border: 0; border-top: 1px solid #EDE9E0; }
</style>
