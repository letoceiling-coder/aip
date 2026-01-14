<template>
    <div class="material-category-tree space-y-4">
        <div class="flex items-center justify-between">
            <h2 class="text-2xl font-semibold">Материалы</h2>
            <button
                @click="showCategoryModal = true"
                class="h-11 px-6 bg-accent/10 backdrop-blur-xl text-accent border border-accent/40 hover:bg-accent/20 rounded-2xl"
            >
                + Добавить категорию
            </button>
        </div>

        <!-- Loading -->
        <div v-if="loading" class="text-center py-12">
            <p class="text-muted-foreground">Загрузка материалов...</p>
        </div>

        <!-- Categories List -->
        <div v-if="!loading" class="space-y-4">
            <div
                v-for="category in categories"
                :key="category.id"
                class="bg-card rounded-lg border border-border p-4"
            >
                <div class="flex items-center justify-between mb-4">
                    <div>
                        <h3 class="text-lg font-semibold">
                            <span v-if="category.icon">{{ category.icon }} </span>{{ category.name }}
                        </h3>
                        <p v-if="category.description" class="text-sm text-muted-foreground mt-1">
                            {{ category.description }}
                        </p>
                        <p v-if="category.media" class="text-xs text-muted-foreground mt-1">
                            📎 Файл: {{ category.media.name }}
                        </p>
                    </div>
                    <div class="flex gap-2">
                        <button
                            @click="editCategory(category)"
                            class="px-3 py-1 text-xs bg-yellow-500 hover:bg-yellow-600 text-white rounded"
                        >
                            Редактировать
                        </button>
                        <button
                            v-if="!category.media_id"
                            @click="selectCategoryFile(category)"
                            class="px-3 py-1 text-xs bg-blue-500 hover:bg-blue-600 text-white rounded"
                        >
                            📎 Файл
                        </button>
                        <button
                            v-else
                            @click="removeCategoryFile(category)"
                            class="px-3 py-1 text-xs bg-orange-500 hover:bg-orange-600 text-white rounded"
                        >
                            🗑️ Удалить файл
                        </button>
                        <button
                            @click="deleteCategory(category)"
                            class="px-3 py-1 text-xs bg-red-500 hover:bg-red-600 text-white rounded"
                        >
                            Удалить
                        </button>
                        <button
                            @click="showMaterialModal(category)"
                            class="px-3 py-1 text-xs bg-green-500 hover:bg-green-600 text-white rounded"
                        >
                            + Материал
                        </button>
                    </div>
                </div>

                <!-- Materials List -->
                <div v-if="category.materials && category.materials.length > 0" class="space-y-2">
                    <div
                        v-for="material in category.materials"
                        :key="material.id"
                        class="flex items-center justify-between p-3 bg-muted/30 rounded-lg"
                    >
                        <div>
                            <p class="font-medium">{{ material.title }}</p>
                            <p v-if="material.description" class="text-sm text-muted-foreground">
                                {{ material.description }}
                            </p>
                            <p class="text-xs text-muted-foreground">
                                Скачиваний: {{ material.download_count || 0 }}
                            </p>
                        </div>
                        <div class="flex gap-2">
                            <button
                                @click="editMaterial(material)"
                                class="px-3 py-1 text-xs bg-blue-500 hover:bg-blue-600 text-white rounded"
                            >
                                Редактировать
                            </button>
                            <button
                                @click="deleteMaterial(material)"
                                class="px-3 py-1 text-xs bg-red-500 hover:bg-red-600 text-white rounded"
                            >
                                Удалить
                            </button>
                        </div>
                    </div>
                </div>
                <div v-else class="text-sm text-muted-foreground p-3">
                    Нет материалов
                </div>
            </div>

            <div v-if="categories.length === 0" class="text-center py-12 text-muted-foreground">
                Категории не найдены. Создайте первую категорию.
            </div>
        </div>

        <!-- Create Category Modal -->
        <div v-if="showCategoryModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/80 backdrop-blur-sm p-4">
            <div class="bg-background border border-border rounded-lg shadow-2xl w-full max-w-md">
                <div class="p-6">
                    <h3 class="text-lg font-semibold mb-4">Создать категорию</h3>
                    <button
                        @click="createCategory"
                        class="w-full h-10 px-4 bg-accent/10 backdrop-blur-xl text-accent border border-accent/40 hover:bg-accent/20 rounded-lg"
                    >
                        Открыть форму создания
                    </button>
                    <button
                        @click="showCategoryModal = false"
                        class="w-full mt-2 h-10 px-4 border border-border bg-background/50 hover:bg-accent/10 rounded-lg"
                    >
                        Отмена
                    </button>
                </div>
            </div>
        </div>

        <!-- Media Picker Modal -->
        <div v-if="showMediaPicker" class="fixed inset-0 z-[60] flex items-center justify-center bg-black/90 p-4">
            <div class="bg-background border border-border rounded-lg shadow-2xl w-full max-w-6xl max-h-[90vh] overflow-hidden flex flex-col">
                <div class="p-6 border-b border-border">
                    <div class="flex items-center justify-between">
                        <h3 class="text-lg font-semibold">Выберите файл из медиа-библиотеки</h3>
                        <button @click="showMediaPicker = false; selectedCategoryForFile = null" class="text-muted-foreground hover:text-foreground">
                            ✕
                        </button>
                    </div>
                </div>
                <div class="flex-1 overflow-hidden">
                    <Media
                        :selection-mode="true"
                        :count-file="1"
                        :selected-files="selectedCategoryForFile?.media_id ? [{ id: selectedCategoryForFile.media_id }] : []"
                        @file-selected="handleFileSelected"
                    />
                </div>
            </div>
        </div>
    </div>
</template>

<script>
import { ref, onMounted } from 'vue'
import { apiGet, apiDelete, apiPut, apiPost } from '../../utils/api'
import Swal from 'sweetalert2'
import MaterialForm from './MaterialForm.vue'
import Media from '../../pages/admin/Media.vue'

export default {
    name: 'MaterialCategoryTree',
    components: {
        MaterialForm,
        Media,
    },
    props: {
        botId: {
            type: [String, Number],
            required: true,
        },
    },
    setup(props) {
        const loading = ref(false)
        const categories = ref([])
        const showCategoryModal = ref(false)
        const showMaterialForm = ref(false)
        const selectedCategory = ref(null)
        const selectedMaterial = ref(null)

        const fetchCategories = async () => {
            loading.value = true
            try {
                const response = await apiGet(`/bot-management/${props.botId}/materials/categories`)
                if (!response.ok) {
                    throw new Error('Ошибка загрузки категорий')
                }

                const data = await response.json()
                categories.value = data.data || []

                // Загружаем материалы для каждой категории
                for (const category of categories.value) {
                    await fetchMaterials(category)
                }
            } catch (err) {
                console.error('Error fetching categories:', err)
            } finally {
                loading.value = false
            }
        }

        const createCategory = async () => {
            const { value: formValues } = await Swal.fire({
                title: 'Создать категорию',
                html: `
                    <input id="swal-icon" class="swal2-input" placeholder="Иконка (эмодзи, например: 🧩)" maxlength="10">
                    <input id="swal-name" class="swal2-input" placeholder="Название" required>
                    <textarea id="swal-description" class="swal2-textarea" placeholder="Описание"></textarea>
                    <input id="swal-order" class="swal2-input" type="number" placeholder="Порядок" value="0">
                `,
                focusConfirm: false,
                showCancelButton: true,
                confirmButtonText: 'Создать',
                cancelButtonText: 'Отмена',
                preConfirm: () => {
                    const name = document.getElementById('swal-name').value
                    if (!name) {
                        Swal.showValidationMessage('Название обязательно')
                        return false
                    }
                    return {
                        name: name,
                        icon: document.getElementById('swal-icon').value || null,
                        description: document.getElementById('swal-description').value,
                        order_index: parseInt(document.getElementById('swal-order').value) || 0,
                    }
                },
            })

            if (formValues) {
                try {
                    const response = await apiPost(`/bot-management/${props.botId}/materials/categories`, formValues)
                    if (!response.ok) {
                        throw new Error('Ошибка создания категории')
                    }

                    await Swal.fire({
                        title: 'Создано',
                        icon: 'success',
                        timer: 2000,
                        showConfirmButton: false,
                        toast: true,
                        position: 'top-end',
                    })

                    fetchCategories()
                    showCategoryModal.value = false
                } catch (err) {
                    Swal.fire({
                        title: 'Ошибка',
                        text: err.message || 'Ошибка создания категории',
                        icon: 'error',
                    })
                }
            }
        }

        const fetchMaterials = async (category) => {
            try {
                const response = await apiGet(`/bot-management/${props.botId}/materials`, {
                    category_id: category.id,
                })
                if (response.ok) {
                    const data = await response.json()
                    category.materials = data.data || []
                }
            } catch (err) {
                console.error('Error fetching materials:', err)
            }
        }

        const deleteCategory = async (category) => {
            const result = await Swal.fire({
                title: 'Удалить категорию?',
                text: `Категория "${category.name}" и все её материалы будут удалены.`,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'Удалить',
                cancelButtonText: 'Отмена',
            })

            if (!result.isConfirmed) return

            try {
                const response = await apiDelete(`/bot-management/${props.botId}/materials/categories/${category.id}`)
                if (!response.ok) {
                    throw new Error('Ошибка удаления категории')
                }

                await Swal.fire({
                    title: 'Удалено',
                    icon: 'success',
                    timer: 2000,
                    showConfirmButton: false,
                    toast: true,
                    position: 'top-end',
                })

                fetchCategories()
            } catch (err) {
                Swal.fire({
                    title: 'Ошибка',
                    text: err.message || 'Ошибка удаления категории',
                    icon: 'error',
                })
            }
        }

        const deleteMaterial = async (material) => {
            const result = await Swal.fire({
                title: 'Удалить материал?',
                text: `Материал "${material.title}" будет удален.`,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'Удалить',
                cancelButtonText: 'Отмена',
            })

            if (!result.isConfirmed) return

            try {
                const response = await apiDelete(`/bot-management/${props.botId}/materials/${material.id}`)
                if (!response.ok) {
                    throw new Error('Ошибка удаления материала')
                }

                await Swal.fire({
                    title: 'Удалено',
                    icon: 'success',
                    timer: 2000,
                    showConfirmButton: false,
                    toast: true,
                    position: 'top-end',
                })

                fetchCategories()
            } catch (err) {
                Swal.fire({
                    title: 'Ошибка',
                    text: err.message || 'Ошибка удаления материала',
                    icon: 'error',
                })
            }
        }

        const editCategory = async (category) => {
            const { value: formValues } = await Swal.fire({
                title: 'Редактировать категорию',
                html: `
                    <input id="swal-icon" class="swal2-input" placeholder="Иконка (эмодзи, например: 🧩)" value="${category.icon || ''}" maxlength="10">
                    <input id="swal-name" class="swal2-input" placeholder="Название" value="${category.name}">
                    <textarea id="swal-description" class="swal2-textarea" placeholder="Описание">${category.description || ''}</textarea>
                    <input id="swal-order" class="swal2-input" type="number" placeholder="Порядок" value="${category.order_index}">
                `,
                focusConfirm: false,
                showCancelButton: true,
                confirmButtonText: 'Сохранить',
                cancelButtonText: 'Отмена',
                preConfirm: () => {
                    return {
                        name: document.getElementById('swal-name').value,
                        icon: document.getElementById('swal-icon').value || null,
                        description: document.getElementById('swal-description').value,
                        order_index: parseInt(document.getElementById('swal-order').value) || 0,
                    }
                },
            })

            if (formValues) {
                try {
                    const response = await apiPut(`/bot-management/${props.botId}/materials/categories/${category.id}`, formValues)
                    if (!response.ok) {
                        throw new Error('Ошибка обновления категории')
                    }

                    await Swal.fire({
                        title: 'Сохранено',
                        icon: 'success',
                        timer: 2000,
                        showConfirmButton: false,
                        toast: true,
                        position: 'top-end',
                    })

                    fetchCategories()
                } catch (err) {
                    Swal.fire({
                        title: 'Ошибка',
                        text: err.message || 'Ошибка обновления категории',
                        icon: 'error',
                    })
                }
            }
        }

        const editMaterial = (material) => {
            selectedMaterial.value = material
            showMaterialForm.value = true
        }

        const showMaterialModal = (category) => {
            selectedCategory.value = category
            selectedMaterial.value = null
            showMaterialForm.value = true
        }

        const showMediaPicker = ref(false)
        const selectedCategoryForFile = ref(null)

        const selectCategoryFile = async (category) => {
            selectedCategoryForFile.value = category
            showMediaPicker.value = true
        }

        const handleFileSelected = async (file) => {
            if (!selectedCategoryForFile.value || !file) return

            try {
                const response = await apiPut(`/bot-management/${props.botId}/materials/categories/${selectedCategoryForFile.value.id}`, {
                    media_id: file.id,
                })

                if (!response.ok) {
                    throw new Error('Ошибка обновления категории')
                }

                showMediaPicker.value = false
                selectedCategoryForFile.value = null

                await Swal.fire({
                    title: 'Сохранено',
                    icon: 'success',
                    timer: 2000,
                    showConfirmButton: false,
                    toast: true,
                    position: 'top-end',
                })

                fetchCategories()
            } catch (err) {
                Swal.fire({
                    title: 'Ошибка',
                    text: err.message || 'Ошибка сохранения файла',
                    icon: 'error',
                })
            }
        }

        const removeCategoryFile = async (category) => {
            try {
                const response = await apiPut(`/bot-management/${props.botId}/materials/categories/${category.id}`, {
                    media_id: null,
                })

                if (!response.ok) {
                    throw new Error('Ошибка удаления файла')
                }

                await Swal.fire({
                    title: 'Файл удален',
                    icon: 'success',
                    timer: 2000,
                    showConfirmButton: false,
                    toast: true,
                    position: 'top-end',
                })

                fetchCategories()
            } catch (err) {
                Swal.fire({
                    title: 'Ошибка',
                    text: err.message || 'Ошибка удаления файла',
                    icon: 'error',
                })
            }
        }

        const formatFileSize = (bytes) => {
            if (!bytes) return '0 B'
            const k = 1024
            const sizes = ['B', 'KB', 'MB', 'GB']
            const i = Math.floor(Math.log(bytes) / Math.log(k))
            return Math.round(bytes / Math.pow(k, i) * 100) / 100 + ' ' + sizes[i]
        }

        onMounted(() => {
            fetchCategories()
        })

        return {
            loading,
            categories,
            showCategoryModal,
            showMaterialForm,
            selectedCategory,
            selectedMaterial,
            fetchCategories,
            createCategory,
            deleteCategory,
            deleteMaterial,
            editCategory,
            editMaterial,
            showMaterialModal,
            selectCategoryFile,
            showMediaPicker,
            selectedCategoryForFile,
            handleFileSelected,
            removeCategoryFile,
        }
    },
}
</script>

