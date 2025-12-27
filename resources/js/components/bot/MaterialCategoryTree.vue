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
                        <h3 class="text-lg font-semibold">{{ category.name }}</h3>
                        <p v-if="category.description" class="text-sm text-muted-foreground mt-1">
                            {{ category.description }}
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
    </div>
</template>

<script>
import { ref, onMounted } from 'vue'
import { apiGet, apiDelete } from '../../utils/api'
import Swal from 'sweetalert2'

export default {
    name: 'MaterialCategoryTree',
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
            // TODO: Реализовать редактирование категории
            Swal.fire({
                title: 'В разработке',
                text: 'Редактирование категорий будет доступно в следующей версии',
                icon: 'info',
            })
        }

        const editMaterial = (material) => {
            // TODO: Реализовать редактирование материала
            Swal.fire({
                title: 'В разработке',
                text: 'Редактирование материалов будет доступно в следующей версии',
                icon: 'info',
            })
        }

        const showMaterialModal = (category) => {
            // TODO: Реализовать модальное окно создания материала
            Swal.fire({
                title: 'В разработке',
                text: 'Создание материалов будет доступно в следующей версии',
                icon: 'info',
            })
        }

        onMounted(() => {
            fetchCategories()
        })

        return {
            loading,
            categories,
            showCategoryModal,
            fetchCategories,
            deleteCategory,
            deleteMaterial,
            editCategory,
            editMaterial,
            showMaterialModal,
        }
    },
}
</script>

