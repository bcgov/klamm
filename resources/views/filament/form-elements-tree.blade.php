{{-- resources/views/filament/form-elements-tree.blade.php --}}
<div class="form-elements-tree">
    <div id="form-tree-container"
        data-form-version-id="{{ $formVersionId }}"
        class="min-h-96 border border-gray-300 rounded-lg p-4">

        <div class="mb-4 flex justify-between items-center">
            <h3 class="text-lg font-medium">Form Elements Structure</h3>
            <div class="space-x-2">
                <button type="button"
                    class="btn btn-primary"
                    onclick="addRootContainer()">
                    Add Root Container
                </button>
                <button type="button"
                    class="btn btn-secondary"
                    onclick="expandAll()">
                    Expand All
                </button>
                <button type="button"
                    class="btn btn-secondary"
                    onclick="collapseAll()">
                    Collapse All
                </button>
            </div>
        </div>

        <div id="tree-view" class="tree-container">
            {{-- Tree will be rendered here via JavaScript --}}
        </div>

        {{-- Element Creation Modal --}}
        <div id="element-modal" class="hidden fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50">
            <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white">
                <div class="mt-3">
                    <h3 class="text-lg font-medium text-gray-900 mb-4">Add Form Element</h3>
                    <form id="element-form">
                        <div class="mb-4">
                            <label class="block text-sm font-medium text-gray-700">Element Type</label>
                            <select id="element-type" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm">
                                <option value="container">Container</option>
                                <option value="text_input">Text Input</option>
                                <option value="checkbox">Checkbox</option>
                                <option value="select">Select</option>
                                <option value="radio">Radio</option>
                                <option value="textarea">Textarea</option>
                                <option value="number">Number Input</option>
                                <option value="date">Date Select</option>
                                <option value="button">Button</option>
                                <option value="html">HTML</option>
                            </select>
                        </div>
                        <div class="mb-4">
                            <label class="block text-sm font-medium text-gray-700">Name</label>
                            <input type="text" id="element-name" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm">
                        </div>
                        <div class="mb-4">
                            <label class="block text-sm font-medium text-gray-700">Description</label>
                            <textarea id="element-description" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm" rows="3"></textarea>
                        </div>
                        <div class="flex justify-end space-x-2">
                            <button type="button" onclick="closeModal()" class="btn btn-secondary">Cancel</button>
                            <button type="button" onclick="createElement()" class="btn btn-primary">Create</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
    .tree-container {
        font-family: Arial, sans-serif;
    }

    .tree-node {
        margin: 2px 0;
        padding: 8px;
        border: 1px solid #e5e7eb;
        border-radius: 4px;
        background-color: #f9fafb;
        cursor: pointer;
        position: relative;
    }

    .tree-node.container {
        background-color: #dbeafe;
        border-color: #3b82f6;
    }

    .tree-node.selected {
        background-color: #fef3c7;
        border-color: #f59e0b;
    }

    .tree-node-content {
        display: flex;
        justify-content: space-between;
        align-items: center;
    }

    .tree-node-info {
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .tree-node-actions {
        display: flex;
        gap: 4px;
    }

    .tree-children {
        margin-left: 20px;
        margin-top: 8px;
        border-left: 2px solid #e5e7eb;
        padding-left: 12px;
    }

    .btn {
        padding: 6px 12px;
        border-radius: 4px;
        border: none;
        cursor: pointer;
        font-size: 12px;
    }

    .btn-primary {
        background-color: #3b82f6;
        color: white;
    }

    .btn-secondary {
        background-color: #6b7280;
        color: white;
    }

    .btn-danger {
        background-color: #ef4444;
        color: white;
    }

    .btn-sm {
        padding: 4px 8px;
        font-size: 11px;
    }

    .element-type-badge {
        background-color: #e5e7eb;
        color: #374151;
        padding: 2px 6px;
        border-radius: 12px;
        font-size: 10px;
        font-weight: 500;
    }

    .drop-zone {
        min-height: 40px;
        border: 2px dashed #d1d5db;
        border-radius: 4px;
        display: flex;
        align-items: center;
        justify-content: center;
        color: #6b7280;
        margin: 4px 0;
    }

    .drop-zone.drag-over {
        border-color: #3b82f6;
        background-color: #dbeafe;
    }
</style>

<script>
    let formVersionId = {
        {
            $formVersionId ?? 'null'
        }
    };
    let selectedNode = null;
    let treeData = [];

    // Initialize the tree when the page loads
    document.addEventListener('DOMContentLoaded', function() {
        if (formVersionId) {
            loadTreeData();
        }
    });

    async function loadTreeData() {
        try {
            const response = await fetch(`/api/form-versions/${formVersionId}/elements`);
            if (response.ok) {
                treeData = await response.json();
                renderTree();
            }
        } catch (error) {
            console.error('Error loading tree data:', error);
        }
    }

    function renderTree() {
        const container = document.getElementById('tree-view');
        container.innerHTML = '';

        if (treeData.length === 0) {
            container.innerHTML = `
            <div class="drop-zone" onclick="addRootContainer()">
                <span>Click to add a root container</span>
            </div>
        `;
            return;
        }

        treeData.forEach(node => {
            container.appendChild(createTreeNode(node));
        });
    }

    function createTreeNode(node) {
        const nodeElement = document.createElement('div');
        nodeElement.className = `tree-node ${node.elementable_type.includes('Container') ? 'container' : ''}`;
        nodeElement.dataset.nodeId = node.id;

        nodeElement.innerHTML = `
        <div class="tree-node-content">
            <div class="tree-node-info">
                <span class="element-type-badge">${getElementTypeLabel(node.elementable_type)}</span>
                <span class="font-medium">${node.name || 'Unnamed Element'}</span>
                ${node.description ? `<span class="text-gray-500 text-sm">- ${node.description}</span>` : ''}
            </div>
            <div class="tree-node-actions">
                ${node.elementable_type.includes('Container') ? 
                    '<button class="btn btn-primary btn-sm" onclick="addChild(' + node.id + ')">Add Child</button>' : ''
                }
                <button class="btn btn-secondary btn-sm" onclick="editElement(' + node.id + ')">Edit</button>
                <button class="btn btn-danger btn-sm" onclick="deleteElement(' + node.id + ')">Delete</button>
            </div>
        </div>
    `;

        // Add children if they exist
        if (node.children && node.children.length > 0) {
            const childrenContainer = document.createElement('div');
            childrenContainer.className = 'tree-children';

            node.children.forEach(child => {
                childrenContainer.appendChild(createTreeNode(child));
            });

            nodeElement.appendChild(childrenContainer);
        } else if (node.elementable_type.includes('Container')) {
            // Add drop zone for empty containers
            const dropZone = document.createElement('div');
            dropZone.className = 'tree-children';
            dropZone.innerHTML = '<div class="drop-zone" onclick="addChild(' + node.id + ')"><span>Drop elements here or click to add</span></div>';
            nodeElement.appendChild(dropZone);
        }

        return nodeElement;
    }

    function getElementTypeLabel(type) {
        const typeMap = {
            'App\\Models\\ContainerFormElement': 'Container',
            'App\\Models\\TextInputFormElement': 'Text Input',
            'App\\Models\\CheckboxInputFormElement': 'Checkbox',
            'App\\Models\\SelectInputFormElement': 'Select',
            'App\\Models\\RadioInputFormElement': 'Radio',
            'App\\Models\\TextareaInputFormElement': 'Textarea',
            'App\\Models\\NumberInputFormElement': 'Number',
            'App\\Models\\DateSelectInputFormElement': 'Date',
            'App\\Models\\ButtonInputFormElement': 'Button',
            'App\\Models\\HTMLFormElement': 'HTML'
        };
        return typeMap[type] || 'Unknown';
    }

    function addRootContainer() {
        // Check if root container already exists
        const hasRoot = treeData.some(node => node.parent_id === null);
        if (hasRoot) {
            alert('A root container already exists. You can only have one root container.');
            return;
        }

        selectedNode = null;
        showModal();
        // Pre-select container type for root
        document.getElementById('element-type').value = 'container';
        document.getElementById('element-type').disabled = true;
    }

    function addChild(parentId) {
        selectedNode = parentId;
        showModal();
        document.getElementById('element-type').disabled = false;
    }

    function showModal() {
        document.getElementById('element-modal').classList.remove('hidden');
        document.getElementById('element-name').value = '';
        document.getElementById('element-description').value = '';
    }

    function closeModal() {
        document.getElementById('element-modal').classList.add('hidden');
        selectedNode = null;
        document.getElementById('element-type').disabled = false;
    }

    async function createElement() {
        const type = document.getElementById('element-type').value;
        const name = document.getElementById('element-name').value;
        const description = document.getElementById('element-description').value;

        if (!name.trim()) {
            alert('Please enter a name for the element');
            return;
        }

        const elementData = {
            name: name,
            description: description,
            form_versions_id: formVersionId,
            parent_id: selectedNode,
            elementable_type: getElementableType(type),
            order: 0
        };

        try {
            const response = await fetch('/api/form-elements', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                },
                body: JSON.stringify(elementData)
            });

            if (response.ok) {
                closeModal();
                loadTreeData(); // Reload the tree
            } else {
                alert('Error creating element');
            }
        } catch (error) {
            console.error('Error creating element:', error);
            alert('Error creating element');
        }
    }

    function getElementableType(type) {
        const typeMap = {
            'container': 'App\\Models\\ContainerFormElement',
            'text_input': 'App\\Models\\TextInputFormElement',
            'checkbox': 'App\\Models\\CheckboxInputFormElement',
            'select': 'App\\Models\\SelectInputFormElement',
            'radio': 'App\\Models\\RadioInputFormElement',
            'textarea': 'App\\Models\\TextareaInputFormElement',
            'number': 'App\\Models\\NumberInputFormElement',
            'date': 'App\\Models\\DateSelectInputFormElement',
            'button': 'App\\Models\\ButtonInputFormElement',
            'html': 'App\\Models\\HTMLFormElement'
        };
        return typeMap[type];
    }

    async function deleteElement(elementId) {
        if (!confirm('Are you sure you want to delete this element and all its children?')) {
            return;
        }

        try {
            const response = await fetch(`/api/form-elements/${elementId}`, {
                method: 'DELETE',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                }
            });

            if (response.ok) {
                loadTreeData(); // Reload the tree
            } else {
                alert('Error deleting element');
            }
        } catch (error) {
            console.error('Error deleting element:', error);
            alert('Error deleting element');
        }
    }

    function editElement(elementId) {
        // This would open a more detailed edit modal
        // For now, just redirect to the element resource
        window.open(`/admin/form-elements/${elementId}/edit`, '_blank');
    }

    function expandAll() {
        // Implementation for expanding all nodes
        console.log('Expand all nodes');
    }

    function collapseAll() {
        // Implementation for collapsing all nodes
        console.log('Collapse all nodes');
    }
</script>