<x-layouts.app :title="__('Dashboard')">
    <x-page-header :title="__('Welcome, :name', ['name' => $user->name])" />
</x-layouts.app>
