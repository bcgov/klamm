@extends('errors::minimal')

@section('title', __('Unauthorized'))

@section('content')
    <div class="flex flex-col items-center justify-center min-h-screen bg-gray-100 dark:bg-gray-900 sm:items-center sm:pt-0">
        <h1 class="text-4xl font-bold mb-4">401 - Unauthorized</h1>
        <p class="mb-4">Sorry! You are not authorized to view this page. If you believe you should have access, please reach out to one of the administrators.</p>
        <a href="{{ url('/') }}" class="px-4 py-2 bg-blue-500 text-white rounded-lg hover:bg-blue-700">Go Home</a>
    </div>
@endsection