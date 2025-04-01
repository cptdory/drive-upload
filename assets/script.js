$(document).ready(function() {
    let files = [];
    let folderStructure = [];
    
    // Handle file selection
    $('#file-input').change(function(e) {
        files = Array.from(e.target.files);
        displayFiles();
    });
    
    // Display selected files
    function displayFiles() {
        $('#file-preview').empty();
        files.forEach((file, index) => {
            $('#file-preview').append(`
                <div class="file-item" data-index="${index}">
                    ${file.name} (${formatFileSize(file.size)})
                </div>
            `);
        });
        updateFolderTree();
    }
    
    // Format file size
    function formatFileSize(bytes) {
        if (bytes === 0) return '0 Bytes';
        const k = 1024;
        const sizes = ['Bytes', 'KB', 'MB', 'GB'];
        const i = Math.floor(Math.log(bytes) / Math.log(k));
        return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
    }
    
    // Add root folder
    $('#add-root-folder').click(function() {
        addFolder(1);
    });
    
    // Add folder
    function addFolder(level, parentFolder = null) {
        const folderId = Date.now();
        const folderElement = $(`
            <div class="folder" data-level="${level}" data-id="${folderId}">
                <input type="text" class="folder-name" placeholder="Folder name">
                <button class="add-subfolder">+ Subfolder</button>
                <button class="remove-folder">× Remove</button>
            </div>
        `);
        
        if (parentFolder) {
            parentFolder.after(folderElement);
        } else {
            $('#folder-structure').append(folderElement);
        }
        
        // Add subfolder
        folderElement.find('.add-subfolder').click(function() {
            addFolder(level + 1, folderElement);
        });
        
        // Remove folder
        folderElement.find('.remove-folder').click(function() {
            folderElement.remove();
            updateFolderTree();
        });
        
        // Update on name change
        folderElement.find('.folder-name').on('input', function() {
            updateFolderTree();
        });
        
        updateFolderTree();
    }
    
    // Update folder tree display
    function updateFolderTree() {
        $('#folder-tree').empty();
        const rootFolders = [];
        
        $('#folder-structure .folder').each(function() {
            const level = parseInt($(this).data('level'));
            const name = $(this).find('.folder-name').val() || 'Untitled folder';
            const id = $(this).data('id');
            
            const folder = { id, name, level, children: [] };
            
            if (level === 1) {
                rootFolders.push(folder);
            } else {
                // Find parent folder
                let parentElement = $(this).prev();
                while (parentElement.length && parseInt(parentElement.data('level')) >= level) {
                    parentElement = parentElement.prev();
                }
                
                if (parentElement.length) {
                    const parentId = parentElement.data('id');
                    const parent = findFolder(rootFolders, parentId);
                    if (parent) parent.children.push(folder);
                }
            }
        });
        
        displayFolderTree(rootFolders);
    }
    
    // Find folder in structure
    function findFolder(folders, id) {
        for (const folder of folders) {
            if (folder.id === id) return folder;
            const found = findFolder(folder.children, id);
            if (found) return found;
        }
        return null;
    }
    
    // Display folder tree
    function displayFolderTree(folders, parentElement = $('#folder-tree')) {
        folders.forEach(folder => {
            const folderElement = $(`
                <div class="folder-item" data-level="${folder.level}" data-id="${folder.id}">
                    <span class="folder-icon">📁</span>
                    <span class="folder-name">${folder.name}</span>
                    <div class="folder-files"></div>
                </div>
            `);
            
            parentElement.append(folderElement);
            
            // Add files to this folder
            const filesElement = folderElement.find('.folder-files');
            files.forEach((file, index) => {
                filesElement.append(`
                    <div class="file-in-folder" draggable="true" data-file-index="${index}">
                        <span class="file-icon">📄</span>
                        <span class="file-name">${file.name}</span>
                    </div>
                `);
            });
            
            // Display subfolders
            if (folder.children.length > 0) {
                const subfolderContainer = $('<div class="subfolders"></div>');
                folderElement.append(subfolderContainer);
                displayFolderTree(folder.children, subfolderContainer);
            }
        });
        
        // Make files draggable
        $('.file-in-folder').each(function() {
            $(this).on('dragstart', function(e) {
                e.originalEvent.dataTransfer.setData('text/plain', $(this).data('file-index'));
            });
        });
        
        // Make folders droppable
        $('.folder-item').each(function() {
            $(this).on('dragover', function(e) {
                e.preventDefault();
                $(this).addClass('drag-over');
            });
            
            $(this).on('dragleave', function() {
                $(this).removeClass('drag-over');
            });
            
            $(this).on('drop', function(e) {
                e.preventDefault();
                $(this).removeClass('drag-over');
                const fileIndex = e.originalEvent.dataTransfer.getData('text/plain');
                $(this).find('.folder-files').append($(`.file-in-folder[data-file-index="${fileIndex}"]`));
            });
        });
    }
    
    // Handle upload
    $('#upload-button').click(async function() {
        if (files.length === 0) {
            alert('Please select at least one file');
            return;
        }
        
        $('#upload-progress').html('<p>Preparing upload...</p>');
        
        try {
            // Get folder structure
            const folders = getFolderStructure();
            
            // Create FormData
            const formData = new FormData();
            files.forEach((file, index) => {
                formData.append(`files[${index}]`, file);
            });
            formData.append('folders', JSON.stringify(folders));
            
            // Send to server
            const response = await fetch('upload.php', {
                method: 'POST',
                body: formData
            });
            
            const result = await response.json();
            
            if (result.success) {
                $('#upload-progress').html(`
                    <div class="upload-success">
                        <h3>Upload Complete!</h3>
                        <p>${result.message}</p>
                        <p>Files uploaded: ${result.uploaded_files}</p>
                        <a href="${result.folder_link}" target="_blank">View in Google Drive</a>
                    </div>
                `);
            } else {
                $('#upload-progress').html(`
                    <div class="upload-error">
                        <h3>Upload Failed</h3>
                        <p>${result.message}</p>
                    </div>
                `);
            }
        } catch (error) {
            $('#upload-progress').html(`
                <div class="upload-error">
                    <h3>Error</h3>
                    <p>${error.message}</p>
                </div>
            `);
        }
    });
    
    // Get folder structure with file assignments
    function getFolderStructure() {
        const folders = [];
        
        $('#folder-structure .folder').each(function() {
            const level = parseInt($(this).data('level'));
            const name = $(this).find('.folder-name').val() || 'Untitled folder';
            const id = $(this).data('id');
            
            const folder = { id, name, level, children: [], files: [] };
            
            if (level === 1) {
                folders.push(folder);
            } else {
                // Find parent folder
                let parentElement = $(this).prev();
                while (parentElement.length && parseInt(parentElement.data('level')) >= level) {
                    parentElement = parentElement.prev();
                }
                
                if (parentElement.length) {
                    const parentId = parentElement.data('id');
                    const parent = findFolderInArray(folders, parentId);
                    if (parent) parent.children.push(folder);
                }
            }
        });
        
        // Assign files to folders
        $('.folder-item').each(function() {
            const folderId = $(this).data('id');
            const folder = findFolderInArray(folders, folderId);
            
            if (folder) {
                $(this).find('.file-in-folder').each(function() {
                    const fileIndex = $(this).data('file-index');
                    folder.files.push(fileIndex);
                });
            }
        });
        
        return folders;
    }
    
    // Find folder in array
    function findFolderInArray(folders, id) {
        for (const folder of folders) {
            if (folder.id === id) return folder;
            const found = findFolderInArray(folder.children, id);
            if (found) return found;
        }
        return null;
    }
});

// Progress bar elements
const progressContainer = document.createElement('div');
progressContainer.style.marginTop = '1.5rem';
progressContainer.style.display = 'none';

const progressBar = document.createElement('div');
progressBar.style.height = '20px';
progressBar.style.backgroundColor = '#2a3a5e';
progressBar.style.borderRadius = '10px';
progressBar.style.overflow = 'hidden';

const progressBarFill = document.createElement('div');
progressBarFill.style.height = '100%';
progressBarFill.style.width = '0%';
progressBarFill.style.backgroundColor = '#1976d2';
progressBarFill.style.transition = 'width 0.3s ease';

const progressText = document.createElement('div');
progressText.style.marginTop = '0.5rem';
progressText.style.textAlign = 'center';
progressText.style.fontSize = '0.9rem';

progressBar.appendChild(progressBarFill);
progressContainer.appendChild(progressBar);
progressContainer.appendChild(progressText);
document.querySelector('.card').appendChild(progressContainer);

// Modify form submission
document.querySelector('form').addEventListener('submit', async function(e) {
    e.preventDefault();
    
    const form = e.target;
    const formData = new FormData(form);
    const submitBtn = form.querySelector('button[type="submit"]');
    
    // Show progress UI
    submitBtn.disabled = true;
    submitBtn.textContent = 'Uploading...';
    progressContainer.style.display = 'block';
    progressText.textContent = 'Preparing upload...';
    
    try {
        const response = await fetch('upload.php', {
            method: 'POST',
            body: formData,
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        });
        
        const result = await response.json();
        
        if (result.success) {
            // Redirect to results page
            window.location.href = 'upload.php?success=1&folder=' + encodeURIComponent(result.folder_name);
        } else {
            progressText.textContent = 'Error: ' + (result.message || 'Upload failed');
            progressBarFill.style.backgroundColor = '#f44336';
            submitBtn.disabled = false;
            submitBtn.textContent = 'Try Again';
        }
    } catch (error) {
        progressText.textContent = 'Network error: ' + error.message;
        progressBarFill.style.backgroundColor = '#f44336';
        submitBtn.disabled = false;
        submitBtn.textContent = 'Try Again';
    }
});

// Progress tracking for the actual upload
const originalFetch = window.fetch;
window.fetch = async function(url, options) {
    if (options && options.body instanceof FormData) {
        const reader = options.body.getReader();
        const contentLength = options.headers['Content-Length'];
        let receivedLength = 0;
        
        const stream = new ReadableStream({
            async start(controller) {
                while (true) {
                    const {done, value} = await reader.read();
                    
                    if (done) break;
                    
                    controller.enqueue(value);
                    receivedLength += value.length;
                    
                    // Update progress
                    if (contentLength) {
                        const percent = Math.round((receivedLength / contentLength) * 100);
                        progressBarFill.style.width = percent + '%';
                        progressText.textContent = `Uploading: ${percent}%`;
                    }
                }
                controller.close();
            }
        });
        
        options.body = new Response(stream).body;
    }
    
    return originalFetch(url, options);
};