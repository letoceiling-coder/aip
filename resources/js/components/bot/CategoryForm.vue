<template>
    <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/80 backdrop-blur-sm p-4">
        <div class="bg-background border border-border rounded-lg shadow-2xl w-full max-w-2xl max-h-[90vh] overflow-y-auto">
            <div class="sticky top-0 bg-background border-b border-border p-6">
                <div class="flex items-center justify-between">
                    <h3 class="text-lg font-semibold">
                        {{ isEditMode ? 'Редактировать категорию' : 'Создать категорию' }}
                    </h3>
                    <button @click="$emit('close')" class="text-muted-foreground hover:text-foreground">
                        ✕
                    </button>
                </div>
            </div>

            <form @submit.prevent="saveCategory" class="p-6 space-y-6">
                <!-- Icon -->
                <div>
                    <label class="block text-sm font-medium mb-2">Иконка (эмодзи)</label>
                    <input
                        v-model="form.icon"
                        type="text"
                        placeholder="🧩"
                        maxlength="10"
                        class="w-full h-10 px-3 border border-border rounded-lg bg-background"
                    />
                    <p class="text-xs text-muted-foreground mt-1">
                        Эмодзи для отображения в боте (например: 🧩, 📚, 📄)
                    </p>
                </div>

                <!-- Name -->
                <div>
                    <label class="block text-sm font-medium mb-2">Название *</label>
                    <input
                        v-model="form.name"
                        type="text"
                        required
                        placeholder="Название категории"
                        class="w-full h-10 px-3 border border-border rounded-lg bg-background"
                    />
                </div>

                <!-- Description -->
                <div>
                    <label class="block text-sm font-medium mb-2">Описание</label>
                    <textarea
                        v-model="form.description"
                        rows="4"
                        placeholder="Описание категории..."
                        class="w-full px-3 py-2 border border-border rounded-lg bg-background resize-none"
                    ></textarea>
                </div>

                <!-- Order Index -->
                <div>
                    <label class="block text-sm font-medium mb-2">Порядок отображения</label>
                    <input
                        v-model.number="form.order_index"
                        type="number"
                        min="0"
                        placeholder="0"
                        class="w-full h-10 px-3 border border-border rounded-lg bg-background"
                    />
                    <p class="text-xs text-muted-foreground mt-1">
                        Чем меньше число, тем выше категория в списке
                    </p>
                </div>

                <!-- Media File -->
                <div>
                    <label class="block text-sm font-medium mb-2">Файл категории</label>
                    <div v-if="form.media_id && selectedMedia" class="mb-3 p-3 bg-muted/30 rounded-lg">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-sm font-medium">{{ selectedMedia.name }}</p>
                                <p class="text-xs text-muted-foreground">{{ formatFileSize(selectedMedia.size) }}</p>
                            </div>
                            <button
                                type="button"
                                @click="removeMedia"
                                class="px-3 py-1 text-xs bg-red-500 hover:bg-red-600 text-white rounded"
                            >
                                Удалить
                            </button>
                        </div>
                    </div>
                    <button
                        type="button"
                        @click="showMediaPicker = true"
                        class="w-full h-10 px-4 border border-border rounded-lg bg-background hover:bg-muted/10"
                    >
                        {{ form.media_id ? 'Изменить файл' : 'Выбрать файл из медиа-библиотеки' }}
                    </button>
                </div>

                <!-- Is Active -->
                <div>
                    <label class="flex items-center gap-2">
                        <input
                            v-model="form.is_active"
                            type="checkbox"
                            class="w-4 h-4"
                        />
                        <span>Категория активна</span>
                    </label>
                </div>

                <!-- Actions -->
                <div class="flex gap-4 pt-4 border-t border-border">
                    <button
                        type="button"
                        @click="$emit('close')"
                        class="flex-1 h-10 px-4 border border-border bg-background/50 hover:bg-accent/10 rounded-lg"
                    >
                        Отмена
                    </button>
                    <button
                        type="submit"
                        :disabled="saving"
                        class="flex-1 h-10 px-4 bg-accent/10 backdrop-blur-xl text-accent border border-accent/40 hover:bg-accent/20 rounded-lg disabled:opacity-50"
                    >
                        {{ saving ? 'Сохранение...' : 'Сохранить' }}
                    </button>
                </div>
            </form>

            <!-- Media Picker Modal -->
            <div v-if="showMediaPicker" class="fixed inset-0 z-[60] flex items-center justify-center bg-black/90 p-4">
                <div class="bg-background border border-border rounded-lg shadow-2xl w-full max-w-6xl max-h-[90vh] flex flex-col">
                    <div class="p-6 border-b border-border flex-shrink-0">
                        <div class="flex items-center justify-between">
                            <h3 class="text-lg font-semibold">Выберите файл из медиа-библиотеки</h3>
                            <button @click="showMediaPicker = false" class="text-muted-foreground hover:text-foreground">
                                ✕
                            </button>
                        </div>
                    </div>
                    <div class="flex-1 overflow-y-auto min-h-0">
                        <Media
                            :selection-mode="true"
                            :count-file="1"
                            :selected-files="form.media_id ? [{ id: form.media_id }] : []"
                            @file-selected="handleFileSelected"
                        />
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script>
import { ref, onMounted } from 'vue'
import { apiGet, apiPost, apiPut } from '../../utils/api'
import Swal from 'sweetalert2'
import Media from '../../pages/admin/Media.vue'

export default {
    name: 'CategoryForm',
    components: {
        Media,
    },
    props: {
        botId: {
            type: [String, Number],
            required: true,
        },
        category: {
            type: Object,
            default: null,
        },
    },
    emits: ['close', 'saved'],
    setup(props, { emit }) {
        const saving = ref(false)
        const showMediaPicker = ref(false)
        const selectedMedia = ref(null)

        const isEditMode = ref(!!props.category)

        const form = ref({
            name: '',
            icon: '',
            description: '',
            order_index: 0,
            media_id: null,
            is_active: true,
        })

        // Инициализация формы при редактировании
        if (props.category) {
            form.value = {
                name: props.category.name || '',
                icon: props.category.icon || '',
                description: props.category.description || '',
                order_index: props.category.order_index || 0,
                media_id: props.category.media_id || null,
                is_active: props.category.is_active !== undefined ? props.category.is_active : true,
            }
            if (props.category.media) {
                selectedMedia.value = props.category.media
            }
        }

        const handleFileSelected = (file) => {
            if (file) {
                form.value.media_id = file.id
                selectedMedia.value = file
                showMediaPicker.value = false
            }
        }

        const removeMedia = () => {
            form.value.media_id = null
            selectedMedia.value = null
        }

        const formatFileSize = (bytes) => {
            if (!bytes) return '0 B'
            const k = 1024
            const sizes = ['B', 'KB', 'MB', 'GB']
            const i = Math.floor(Math.log(bytes) / Math.log(k))
            return Math.round(bytes / Math.pow(k, i) * 100) / 100 + ' ' + sizes[i]
        }

        const saveCategory = async () => {
            saving.value = true
            try {
                const data = {
                    name: form.value.name,
                    icon: form.value.icon || null,
                    description: form.value.description || null,
                    order_index: form.value.order_index || 0,
                    media_id: form.value.media_id || null,
                    is_active: form.value.is_active,
                }

                let response
                if (isEditMode.value) {
                    response = await apiPut(`/bot-management/${props.botId}/materials/categories/${props.category.id}`, data)
                } else {
                    response = await apiPost(`/bot-management/${props.botId}/materials/categories`, data)
                }

                if (!response.ok) {
                    const errorData = await response.json().catch(() => ({}))
                    throw new Error(errorData.message || 'Ошибка сохранения категории')
                }

                await Swal.fire({
                    title: 'Сохранено',
                    icon: 'success',
                    timer: 2000,
                    showConfirmButton: false,
                    toast: true,
                    position: 'top-end',
                })

                emit('saved')
                emit('close')
            } catch (err) {
                Swal.fire({
                    title: 'Ошибка',
                    text: err.message || 'Ошибка сохранения категории',
                    icon: 'error',
                    confirmButtonText: 'ОК',
                })
            } finally {
                saving.value = false
            }
        }

        return {
            saving,
            showMediaPicker,
            selectedMedia,
            isEditMode,
            form,
            handleFileSelected,
            removeMedia,
            formatFileSize,
            saveCategory,
        }
    },
}
</script>

