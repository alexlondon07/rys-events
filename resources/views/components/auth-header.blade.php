@props([
    'title',
    'description',
])

<div class="flex w-full flex-col text-center">
    <flux:heading size="xl" level="1" class="font-display !text-[#17150F]">{{ $title }}</flux:heading>
    <flux:subheading class="!text-[#5F584A]">{{ $description }}</flux:subheading>
</div>
