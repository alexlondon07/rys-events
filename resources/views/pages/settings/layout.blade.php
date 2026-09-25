<div>
    <flux:heading class="!text-[#17150F]">{{ $heading ?? '' }}</flux:heading>
    <flux:subheading class="!text-[#5F584A]">{{ $subheading ?? '' }}</flux:subheading>

    <div class="mt-5 w-full max-w-lg">
        {{ $slot }}
    </div>
</div>
