<header class="report-header">
    <div class="flex items-center gap-3">
        @if (! empty($company?->logo_path))
            <img src="{{ asset('storage/'.$company->logo_path) }}" alt="{{ $company->name }}" class="h-8 w-auto object-contain">
        @else
            <span class="flex size-9 items-center justify-center rounded-full border border-[#C9A043] text-[10px] font-bold text-[#E2C274]">RYS</span>
        @endif
        <span class="font-display text-sm font-bold">{{ $company?->name ?: 'Grupo RYS S.A.S.' }}</span>
    </div>
    <span class="text-[10px] uppercase tracking-[0.12em] text-[#CFC8B8]">Informe de actividades</span>
</header>
