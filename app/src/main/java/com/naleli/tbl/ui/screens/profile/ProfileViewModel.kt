package com.naleli.tbl.ui.screens.profile

import androidx.lifecycle.ViewModel
import androidx.lifecycle.viewModelScope
import com.naleli.tbl.data.db.entity.LearnerProfileEntity
import com.naleli.tbl.data.repository.ProfileRepository
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.coroutines.launch
import java.time.LocalDate

data class ProfileFormState(
    val firstName: String = "",
    val surname: String = "",
    val studentNumber: String = "",
    val email: String = "",
    val phone: String = "",
    val startDate: LocalDate = LocalDate.now(),
    val isSaving: Boolean = false,
    val savedProfile: LearnerProfileEntity? = null,
    val validationError: String? = null,
) {
    val canSave: Boolean get() = firstName.isNotBlank() && surname.isNotBlank()
}

class ProfileViewModel(private val repository: ProfileRepository) : ViewModel() {
    private val _state = MutableStateFlow(ProfileFormState())
    val state: StateFlow<ProfileFormState> = _state.asStateFlow()

    init {
        viewModelScope.launch {
            repository.getProfile()?.let { profile ->
                _state.value = ProfileFormState(
                    firstName = profile.firstName,
                    surname = profile.surname,
                    studentNumber = profile.studentNumber.orEmpty(),
                    email = profile.email.orEmpty(),
                    phone = profile.phone.orEmpty(),
                    startDate = ProfileRepository.startDateOf(profile),
                    savedProfile = profile,
                )
            }
        }
    }

    fun onFirstNameChange(value: String) { _state.value = _state.value.copy(firstName = value, validationError = null) }
    fun onSurnameChange(value: String) { _state.value = _state.value.copy(surname = value, validationError = null) }
    fun onStudentNumberChange(value: String) { _state.value = _state.value.copy(studentNumber = value) }
    fun onEmailChange(value: String) { _state.value = _state.value.copy(email = value) }
    fun onPhoneChange(value: String) { _state.value = _state.value.copy(phone = value) }
    fun onStartDateChange(value: LocalDate) { _state.value = _state.value.copy(startDate = value) }

    fun save(programmeId: String, onSaved: () -> Unit) {
        val current = _state.value
        if (!current.canSave) {
            _state.value = current.copy(validationError = "First name and surname are required")
            return
        }
        _state.value = current.copy(isSaving = true)
        viewModelScope.launch {
            val existing = repository.getProfile()
            val result = if (existing == null) {
                repository.createProfile(
                    firstName = current.firstName,
                    surname = current.surname,
                    studentNumber = current.studentNumber.ifBlank { null },
                    email = current.email.ifBlank { null },
                    phone = current.phone.ifBlank { null },
                    programmeId = programmeId,
                    startDate = current.startDate,
                )
            } else {
                val updated = existing.copy(
                    firstName = current.firstName.trim(),
                    surname = current.surname.trim(),
                    studentNumber = current.studentNumber.trim().ifBlank { null },
                    email = current.email.trim().ifBlank { null },
                    phone = current.phone.trim().ifBlank { null },
                    startDateEpochDay = current.startDate.toEpochDay(),
                )
                repository.updateProfile(updated)
                updated
            }
            _state.value = _state.value.copy(isSaving = false, savedProfile = result)
            onSaved()
        }
    }
}
