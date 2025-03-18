<div class="space-y-4 mt-6">
    <h2 class="text-lg font-semibold">{{ $this->heading }}</h2>
    <p>{{ $this->subheading }}</p>
    <p>{{ $this->getStarted }}</p>

    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
        <a href="{{ url('/admin') }}" class="block p-6 bg-white dark:bg-gray-800 border rounded-lg shadow hover:bg-gray-100 dark:hover:bg-gray-700">
            <h3 class="text-xl font-semibold text-black dark:text-white">Admin Panel</h3>
            <p class="text-gray-500 dark:text-gray-400">Administrative features and settings</p>
        </a>
        <a href="{{ url('/forms') }}" class="block p-6 bg-white dark:bg-gray-800 border rounded-lg shadow hover:bg-gray-100 dark:hover:bg-gray-700">
            <h3 class="text-xl font-semibold text-black dark:text-white">Forms Panel</h3>
            <p class="text-gray-500 dark:text-gray-400">Forms team metadata and form building</p>
        </a>
        <a href="{{ url('/bre') }}" class="block p-6 bg-white dark:bg-gray-800 border rounded-lg shadow hover:bg-gray-100 dark:hover:bg-gray-700">
            <h3 class="text-xl font-semibold text-black dark:text-white">BRE Panel</h3>
            <p class="text-gray-500 dark:text-gray-400">Business Rules Engine</p>
        </a>
        <a href="{{ url('/fodig') }}" class="block p-6 bg-white dark:bg-gray-800 border rounded-lg shadow hover:bg-gray-100 dark:hover:bg-gray-700">
            <h3 class="text-xl font-semibold text-black dark:text-white">FODIG Panel</h3>
            <p class="text-gray-500 dark:text-gray-400">Financial Operations and Data Integration Gateway</p>
        </a>

        <a href="{{ url('/reports') }}" class="block p-6 bg-white dark:bg-gray-800 border rounded-lg shadow hover:bg-gray-100 dark:hover:bg-gray-700">
            <h3 class="text-xl font-semibold text-black dark:text-white">Report Label Dictionary</h3>
            <p class="text-gray-500 dark:text-gray-400">Reports labeling and metadata cataloguing</p>
        </a>
    </div>
</div>