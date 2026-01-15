<template>
    <div class="material-category-tree space-y-4">
        <div class="flex items-center justify-between">
            <h2 class="text-2xl font-semibold">Материалы</h2>
            <button
                @click="createCategory"
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
        <div v-if="!loading" class="space-y-4" ref="categoriesContainer">
            <div
                v-for="(category, categoryIndex) in categories"
                :key="category.id"
                :draggable="true"
                @dragstart="handleCategoryDragStart($event, categoryIndex)"
                @dragover.prevent="handleCategoryDragOver($event, categoryIndex)"
                @drop="handleCategoryDrop($event, categoryIndex)"
                @dragend="handleCategoryDragEnd"
                :class="[
                    'bg-card rounded-lg border border-border p-4 cursor-move transition-all',
                    draggedCategoryIndex === categoryIndex ? 'opacity-50 border-accent' : ''
                ]"
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
                            class="px-3 py-1 text-xs bg-green-500 hover:bg-green-600 text-white rounded"
                        >
                            ✏️ Редактировать
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
                    </div>
                </div>

                <!-- Materials List -->
                <div v-if="category.materials && category.materials.length > 0" class="space-y-2">
                    <div
                        v-for="(material, materialIndex) in category.materials"
                        :key="material.id"
                        :draggable="true"
                        @dragstart="handleMaterialDragStart($event, categoryIndex, materialIndex)"
                        @dragover.prevent="handleMaterialDragOver($event, categoryIndex, materialIndex)"
                        @drop="handleMaterialDrop($event, categoryIndex, materialIndex)"
                        @dragend="handleMaterialDragEnd"
                        :class="[
                            'flex items-center justify-between p-3 bg-muted/30 rounded-lg cursor-move transition-all',
                            draggedMaterialIndex === materialIndex && draggedCategoryIndex === categoryIndex ? 'opacity-50 border-2 border-accent' : ''
                        ]"
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
                    <button
                        @click="showMaterialModal(category)"
                        class="ml-2 px-3 py-1 text-xs bg-accent/10 text-accent border border-accent/40 hover:bg-accent/20 rounded"
                    >
                        + Добавить материал
                    </button>
                </div>
            </div>

            <div v-if="categories.length === 0" class="text-center py-12 text-muted-foreground">
                Категории не найдены. Создайте первую категорию.
            </div>
        </div>

        <!-- Material Form Modal -->
        <MaterialForm
            v-if="showMaterialForm"
            :bot-id="botId"
            :category-id="selectedCategory?.id || selectedMaterial?.category_id"
            :material="selectedMaterial"
            @close="handleMaterialFormClose"
            @saved="handleMaterialSaved"
        />

        <!-- Category Form Modal -->
        <CategoryForm
            v-if="showCategoryForm"
            :bot-id="botId"
            :category="selectedCategoryForEdit"
            @close="handleCategoryFormClose"
            @saved="handleCategorySaved"
        />

        <!-- Media Picker Modal -->
        <div v-if="showMediaPicker" class="fixed inset-0 z-[60] flex items-center justify-center bg-black/90 p-4">
            <div class="bg-background border border-border rounded-lg shadow-2xl w-full max-w-6xl max-h-[90vh] flex flex-col">
                <div class="p-6 border-b border-border flex-shrink-0">
                    <div class="flex items-center justify-between">
                        <h3 class="text-lg font-semibold">Выберите файл из медиа-библиотеки</h3>
                        <button @click="showMediaPicker = false; selectedCategoryForFile = null" class="text-muted-foreground hover:text-foreground">
                            ✕
                        </button>
                    </div>
                </div>
                <div class="flex-1 overflow-y-auto min-h-0">
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
import { ref, onMounted, nextTick } from 'vue'
import { apiGet, apiDelete, apiPut, apiPost } from '../../utils/api'
import Swal from 'sweetalert2'
import MaterialForm from './MaterialForm.vue'
import CategoryForm from './CategoryForm.vue'
import Media from '../../pages/admin/Media.vue'

export default {
    name: 'MaterialCategoryTree',
    components: {
        MaterialForm,
        CategoryForm,
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
        const showCategoryForm = ref(false)
        const showMaterialForm = ref(false)
        const selectedCategory = ref(null)
        const selectedCategoryForEdit = ref(null)
        const selectedMaterial = ref(null)
        
        // Drag and drop state
        const draggedCategoryIndex = ref(null)
        const draggedMaterialIndex = ref(null)
        const draggedMaterialCategoryIndex = ref(null)
        const categoriesContainer = ref(null)

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

        const createCategory = () => {
            selectedCategoryForEdit.value = null
            showCategoryForm.value = true
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

        const editCategory = (category) => {
            selectedCategoryForEdit.value = category
            showCategoryForm.value = true
        }
        
        const handleCategoryFormClose = () => {
            showCategoryForm.value = false
            selectedCategoryForEdit.value = null
        }
        
        const handleCategorySaved = () => {
                    fetchCategories()
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

        const handleMaterialFormClose = () => {
            showMaterialForm.value = false
            selectedMaterial.value = null
            selectedCategory.value = null
        }

        const handleMaterialSaved = () => {
            fetchCategories()
        }

        // Drag and drop для категорий
        const handleCategoryDragStart = (event, index) => {
            draggedCategoryIndex.value = index
            event.dataTransfer.effectAllowed = 'move'
            event.dataTransfer.setData('text/html', event.target.outerHTML)
        }

        const handleCategoryDragOver = (event, index) => {
            event.preventDefault()
            if (draggedCategoryIndex.value !== null && draggedCategoryIndex.value !== index) {
                event.dataTransfer.dropEffect = 'move'
            }
        }

        const handleCategoryDrop = async (event, targetIndex) => {
            event.preventDefault()
            if (draggedCategoryIndex.value === null || draggedCategoryIndex.value === targetIndex) {
                return
            }

            const sourceIndex = draggedCategoryIndex.value
            const newCategories = [...categories.value]
            const [movedCategory] = newCategories.splice(sourceIndex, 1)
            newCategories.splice(targetIndex, 0, movedCategory)

            // Обновляем order_index для всех категорий
            const updatedCategories = newCategories.map((cat, idx) => ({
                id: cat.id,
                order_index: idx,
            }))

            try {
                const response = await apiPost(`/bot-management/${props.botId}/materials/categories/update-positions`, {
                    categories: updatedCategories,
                })

                if (response.ok) {
                    categories.value = newCategories
                    await Swal.fire({
                        title: 'Позиция обновлена',
                        icon: 'success',
                        timer: 1500,
                        showConfirmButton: false,
                        toast: true,
                        position: 'top-end',
                    })
                }
            } catch (err) {
                console.error('Error updating category positions:', err)
                Swal.fire({
                    title: 'Ошибка',
                    text: 'Не удалось обновить позицию категории',
                    icon: 'error',
                })
                fetchCategories() // Восстанавливаем исходный порядок
            }
        }

        const handleCategoryDragEnd = () => {
            draggedCategoryIndex.value = null
        }

        // Drag and drop для материалов
        const handleMaterialDragStart = (event, categoryIndex, materialIndex) => {
            draggedMaterialIndex.value = materialIndex
            draggedMaterialCategoryIndex.value = categoryIndex
            event.dataTransfer.effectAllowed = 'move'
            event.dataTransfer.setData('text/html', event.target.outerHTML)
        }

        const handleMaterialDragOver = (event, categoryIndex, materialIndex) => {
            event.preventDefault()
            if (
                draggedMaterialIndex.value !== null &&
                draggedMaterialCategoryIndex.value === categoryIndex &&
                draggedMaterialIndex.value !== materialIndex
            ) {
                event.dataTransfer.dropEffect = 'move'
            }
        }

        const handleMaterialDrop = async (event, targetCategoryIndex, targetMaterialIndex) => {
            event.preventDefault()
            if (
                draggedMaterialIndex.value === null ||
                draggedMaterialCategoryIndex.value !== targetCategoryIndex ||
                draggedMaterialIndex.value === targetMaterialIndex
            ) {
                return
            }

            const sourceIndex = draggedMaterialIndex.value
            const category = categories.value[targetCategoryIndex]
            const newMaterials = [...category.materials]
            const [movedMaterial] = newMaterials.splice(sourceIndex, 1)
            newMaterials.splice(targetMaterialIndex, 0, movedMaterial)

            // Обновляем order_index для всех материалов в категории
            const updatedMaterials = newMaterials.map((mat, idx) => ({
                id: mat.id,
                order_index: idx,
            }))

            try {
                const response = await apiPost(`/bot-management/${props.botId}/materials/update-positions`, {
                    materials: updatedMaterials,
                })

                if (response.ok) {
                    category.materials = newMaterials
                    await Swal.fire({
                        title: 'Позиция обновлена',
                        icon: 'success',
                        timer: 1500,
                        showConfirmButton: false,
                        toast: true,
                        position: 'top-end',
                    })
                }
            } catch (err) {
                console.error('Error updating material positions:', err)
                Swal.fire({
                    title: 'Ошибка',
                    text: 'Не удалось обновить позицию материала',
                    icon: 'error',
                })
                fetchCategories() // Восстанавливаем исходный порядок
            }
        }

        const handleMaterialDragEnd = () => {
            draggedMaterialIndex.value = null
            draggedMaterialCategoryIndex.value = null
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
            showCategoryForm,
            showMaterialForm,
            selectedCategory,
            selectedCategoryForEdit,
            selectedMaterial,
            draggedCategoryIndex,
            draggedMaterialIndex,
            categoriesContainer,
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
            handleMaterialFormClose,
            handleMaterialSaved,
            handleCategoryFormClose,
            handleCategorySaved,
            handleCategoryDragStart,
            handleCategoryDragOver,
            handleCategoryDrop,
            handleCategoryDragEnd,
            handleMaterialDragStart,
            handleMaterialDragOver,
            handleMaterialDrop,
            handleMaterialDragEnd,
        }
    },
}
</script>

