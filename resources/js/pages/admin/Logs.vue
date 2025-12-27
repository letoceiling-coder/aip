<template>
    <div class="logs-page space-y-6">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-3xl font-semibold text-foreground">Логи системы</h1>
                <p class="text-muted-foreground mt-1">Просмотр и управление логами приложения</p>
            </div>
        </div>

        <!-- Панель управления -->
        <div class="bg-card rounded-lg border border-border p-4">
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                <!-- Выбор файла лога -->
                <div class="md:col-span-2">
                    <label class="block text-sm font-medium mb-2">Файл лога:</label>
                    <select
                        v-model="selectedLogFile"
                        @change="handleLogFileChange"
                        class="w-full h-10 px-3 border border-border rounded bg-background text-sm"
                    >
                        <option v-for="file in logFiles" :key="file" :value="file">
                            {{ file }}
                        </option>
                    </select>
                </div>

                <!-- Количество строк -->
                <div>
                    <label class="block text-sm font-medium mb-2">Строк:</label>
                    <input
                        type="number"
                        v-model.number="linesCount"
                        min="100"
                        max="5000"
                        step="100"
                        class="w-full h-10 px-3 border border-border rounded bg-background text-sm"
                    />
                </div>

                <!-- Кнопки управления -->
                <div class="flex items-end gap-2">
                    <button
                        @click="loadLogs"
                        :disabled="loading"
                        class="flex-1 h-10 px-4 bg-primary text-primary-foreground rounded hover:bg-primary/90 disabled:opacity-50 disabled:cursor-not-allowed text-sm font-medium"
                    >
                        {{ loading ? 'Загрузка...' : 'Обновить' }}
                    </button>
                    <button
                        @click="clearLogs"
                        :disabled="clearing"
                        class="flex-1 h-10 px-4 bg-destructive text-destructive-foreground rounded hover:bg-destructive/90 disabled:opacity-50 disabled:cursor-not-allowed text-sm font-medium"
                    >
                        {{ clearing ? 'Очистка...' : 'Очистить' }}
                    </button>
                </div>
            </div>
        </div>

        <!-- Информационная панель -->
        <div v-if="logInfo.file" class="bg-card rounded-lg border border-border p-4">
            <div class="flex flex-wrap gap-6 text-sm">
                <div>
                    <span class="text-muted-foreground">Файл:</span>
                    <span class="ml-2 font-medium">{{ logInfo.file }}</span>
                </div>
                <div>
                    <span class="text-muted-foreground">Размер:</span>
                    <span class="ml-2 font-medium">{{ logInfo.file_size_formatted }}</span>
                </div>
                <div>
                    <span class="text-muted-foreground">Строк:</span>
                    <span class="ml-2 font-medium">{{ logInfo.lines_count }}</span>
                </div>
                <div>
                    <span class="text-muted-foreground">Обновлено:</span>
                    <span class="ml-2 font-medium">{{ lastUpdate }}</span>
                </div>
            </div>
        </div>

        <!-- Ошибка -->
        <div v-if="error" class="p-4 bg-destructive/10 border border-destructive/20 rounded-lg">
            <p class="text-destructive">{{ error }}</p>
        </div>

        <!-- Содержимое логов -->
        <div class="bg-card rounded-lg border border-border">
            <div class="p-4 border-b border-border">
                <h2 class="text-lg font-semibold">Содержимое лога</h2>
            </div>
            <div class="p-4">
                <div v-if="loading && !logContent" class="flex items-center justify-center py-12">
                    <p class="text-muted-foreground">Загрузка логов...</p>
                </div>
                <div v-else-if="!logContent && !loading" class="flex items-center justify-center py-12">
                    <p class="text-muted-foreground">Выберите файл лога и нажмите "Обновить"</p>
                </div>
                <pre v-else class="bg-[#1e1e1e] text-[#d4d4d4] p-4 rounded overflow-x-auto text-xs font-mono leading-relaxed whitespace-pre-wrap break-words" style="max-height: 70vh; overflow-y: auto;">{{ logContent }}</pre>
            </div>
        </div>
    </div>
</template>

<script>
import { ref, onMounted } from 'vue'
import axios from 'axios'
import Swal from 'sweetalert2'

export default {
    name: 'Logs',
    setup() {
        const loading = ref(false)
        const clearing = ref(false)
        const error = ref(null)
        const logFiles = ref([])
        const selectedLogFile = ref('laravel.log')
        const linesCount = ref(500)
        const logContent = ref('')
        const logInfo = ref({})
        const lastUpdate = ref('')

        const getAuthHeaders = () => {
            const token = localStorage.getItem('token')
            return {
                'Authorization': `Bearer ${token}`,
                'Accept': 'application/json',
            }
        }

        const fetchLogFiles = async () => {
            try {
                const response = await axios.get('/api/logs/files', {
                    headers: getAuthHeaders()
                })

                if (response.data.files) {
                    logFiles.value = response.data.files
                    if (logFiles.value.length > 0 && !logFiles.value.includes(selectedLogFile.value)) {
                        selectedLogFile.value = logFiles.value[0]
                    }
                }
            } catch (err) {
                console.error('Error fetching log files:', err)
                error.value = 'Ошибка при загрузке списка файлов логов'
            }
        }

        const loadLogs = async () => {
            loading.value = true
            error.value = null

            try {
                const response = await axios.get('/api/logs', {
                    params: {
                        file: selectedLogFile.value,
                        lines: linesCount.value
                    },
                    headers: getAuthHeaders()
                })

                if (response.data.error) {
                    error.value = response.data.error
                    logContent.value = ''
                    logInfo.value = {}
                } else {
                    logContent.value = response.data.content || ''
                    logInfo.value = {
                        file: response.data.file,
                        file_size_formatted: response.data.file_size_formatted,
                        lines_count: response.data.lines_count,
                    }
                    lastUpdate.value = new Date().toLocaleString('ru-RU')
                }
            } catch (err) {
                error.value = err.response?.data?.error || 'Ошибка при загрузке логов'
                console.error('Error loading logs:', err)
            } finally {
                loading.value = false
            }
        }

        const clearLogs = async () => {
            const result = await Swal.fire({
                title: 'Очистить логи?',
                text: `Вы уверены, что хотите очистить файл "${selectedLogFile.value}"? Это действие нельзя отменить.`,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#ef4444',
                cancelButtonColor: '#6b7280',
                confirmButtonText: 'Да, очистить',
                cancelButtonText: 'Отмена',
            })

            if (!result.isConfirmed) {
                return
            }

            clearing.value = true
            error.value = null

            try {
                const response = await axios.post('/api/logs/clear', {
                    file: selectedLogFile.value
                }, {
                    headers: getAuthHeaders()
                })

                if (response.data.success) {
                    await Swal.fire({
                        title: 'Успешно',
                        text: 'Лог-файл успешно очищен',
                        icon: 'success',
                        timer: 2000,
                        showConfirmButton: false,
                    })

                    // Перезагружаем логи
                    await loadLogs()
                } else {
                    error.value = response.data.error || 'Ошибка при очистке логов'
                }
            } catch (err) {
                error.value = err.response?.data?.error || 'Ошибка при очистке логов'
                console.error('Error clearing logs:', err)
            } finally {
                clearing.value = false
            }
        }

        const handleLogFileChange = () => {
            logContent.value = ''
            logInfo.value = {}
        }

        onMounted(async () => {
            await fetchLogFiles()
            await loadLogs()
        })

        return {
            loading,
            clearing,
            error,
            logFiles,
            selectedLogFile,
            linesCount,
            logContent,
            logInfo,
            lastUpdate,
            loadLogs,
            clearLogs,
            handleLogFileChange,
        }
    },
}
</script>

<style scoped>
.logs-page {
    min-height: 100vh;
}

pre {
    font-family: 'Courier New', Courier, monospace;
}
</style>

