// Custom JavaScript for GemLogin Scheduler

document.addEventListener('DOMContentLoaded', function() {
    // Initialize tooltips
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });

    // Initialize popovers
    var popoverTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="popover"]'));
    var popoverList = popoverTriggerList.map(function (popoverTriggerEl) {
        return new bootstrap.Popover(popoverTriggerEl);
    });

    // Profile selection in create schedule page
    const selectAllBtn = document.getElementById('select-all-profiles');
    const deselectAllBtn = document.getElementById('deselect-all-profiles');
    
    if (selectAllBtn) {
        selectAllBtn.addEventListener('click', function(e) {
            e.preventDefault();
            document.querySelectorAll('input[name="profile_ids[]"]').forEach(function(checkbox) {
                checkbox.checked = true;
            });
        });
    }
    
    if (deselectAllBtn) {
        deselectAllBtn.addEventListener('click', function(e) {
            e.preventDefault();
            document.querySelectorAll('input[name="profile_ids[]"]').forEach(function(checkbox) {
                checkbox.checked = false;
            });
        });
    }

    // Date and time picker initialization
    const datetimeInputs = document.querySelectorAll('.datetimepicker');
    if (datetimeInputs.length > 0) {
        datetimeInputs.forEach(function(input) {
            $(input).datetimepicker({
                format: 'YYYY-MM-DD HH:mm',
                icons: {
                    time: 'far fa-clock',
                    date: 'far fa-calendar',
                    up: 'fas fa-arrow-up',
                    down: 'fas fa-arrow-down',
                    previous: 'fas fa-chevron-left',
                    next: 'fas fa-chevron-right',
                    today: 'fas fa-calendar-check',
                    clear: 'far fa-trash-alt',
                    close: 'fas fa-times'
                }
            });
        });
    }

    // Auto calculate end time when start time changes
    const startTimeInput = document.getElementById('start_time');
    const endTimeInput = document.getElementById('end_time');
    
    if (startTimeInput && endTimeInput) {
        startTimeInput.addEventListener('change', function() {
            // Set end time to 24 hours after start time
            if (this.value) {
                const startDate = new Date(this.value);
                const endDate = new Date(startDate.getTime() + (24 * 60 * 60 * 1000)); // Add 24 hours
                
                // Format end date to YYYY-MM-DD HH:MM
                const year = endDate.getFullYear();
                const month = String(endDate.getMonth() + 1).padStart(2, '0');
                const day = String(endDate.getDate()).padStart(2, '0');
                const hours = String(endDate.getHours()).padStart(2, '0');
                const minutes = String(endDate.getMinutes()).padStart(2, '0');
                
                endTimeInput.value = `${year}-${month}-${day} ${hours}:${minutes}`;
            }
        });
    }

    // Initialize table sorting
    const tableFilters = document.querySelectorAll('.table-filter');
    if (tableFilters.length > 0) {
        tableFilters.forEach(function(filter) {
            filter.addEventListener('change', function() {
                const form = this.closest('form');
                if (form) {
                    form.submit();
                }
            });
        });
    }

    // Confirm delete actions
    const deleteButtons = document.querySelectorAll('.btn-delete');
    if (deleteButtons.length > 0) {
        deleteButtons.forEach(function(button) {
            button.addEventListener('click', function(e) {
                if (!confirm('Are you sure you want to delete this item? This action cannot be undone.')) {
                    e.preventDefault();
                }
            });
        });
    }

    // Search functionality
    const searchInput = document.getElementById('search');
    if (searchInput) {
        const searchForm = searchInput.closest('form');
        let typingTimer;
        const doneTypingInterval = 500; // ms
        
        searchInput.addEventListener('keyup', function() {
            clearTimeout(typingTimer);
            typingTimer = setTimeout(function() {
                searchForm.submit();
            }, doneTypingInterval);
        });
        
        searchInput.addEventListener('keydown', function() {
            clearTimeout(typingTimer);
        });
    }

    // Profile filter by group
    const groupFilter = document.getElementById('group_filter');
    if (groupFilter) {
        groupFilter.addEventListener('change', function() {
            const form = this.closest('form');
            if (form) {
                form.submit();
            }
        });
    }

    // Show/hide script parameters based on script selection
    const scriptSelect = document.getElementById('script_id');
    if (scriptSelect) {
        const parametersContainer = document.getElementById('script-parameters');
        
        function updateScriptParameters() {
            const scriptId = scriptSelect.value;
            if (!scriptId || !parametersContainer) return;
            
            // Fetch script parameters via AJAX
            fetch(`ajax/get_script_parameters.php?script_id=${scriptId}`)
                .then(response => response.json())
                .then(data => {
                    parametersContainer.innerHTML = '';
                    
                    if (data.success && data.parameters.length > 0) {
                        parametersContainer.style.display = 'block';
                        
                        data.parameters.forEach(param => {
                            const formGroup = document.createElement('div');
                            formGroup.className = 'mb-3';
                            
                            const label = document.createElement('label');
                            label.htmlFor = `param_${param.name}`;
                            label.className = param.required ? 'form-label form-required' : 'form-label';
                            label.textContent = param.label || param.name;
                            
                            const input = document.createElement('input');
                            input.type = param.type === 'number' ? 'number' : 'text';
                            input.className = 'form-control';
                            input.id = `param_${param.name}`;
                            input.name = `parameters[${param.name}]`;
                            input.value = param.defaultValue || '';
                            if (param.required) input.required = true;
                            
                            if (param.description) {
                                const helpText = document.createElement('div');
                                helpText.className = 'form-text';
                                helpText.textContent = param.description;
                                formGroup.appendChild(helpText);
                            }
                            
                            formGroup.appendChild(label);
                            formGroup.appendChild(input);
                            parametersContainer.appendChild(formGroup);
                        });
                    } else {
                        parametersContainer.style.display = 'none';
                    }
                })
                .catch(error => {
                    console.error('Error fetching script parameters:', error);
                    parametersContainer.style.display = 'none';
                });
        }
        
        scriptSelect.addEventListener('change', updateScriptParameters);
        
        // Initialize on page load
        if (scriptSelect.value) {
            updateScriptParameters();
        }
    }
});
