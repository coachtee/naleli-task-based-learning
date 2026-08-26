package com.naleli.tbl.ui.screens.profile

import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.verticalScroll
import androidx.compose.material3.Button
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.OutlinedTextField
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.collectAsState
import androidx.compose.runtime.getValue
import androidx.compose.ui.Modifier
import androidx.compose.ui.res.stringResource
import androidx.compose.ui.text.input.KeyboardType
import androidx.compose.ui.unit.dp
import androidx.lifecycle.viewmodel.compose.viewModel
import androidx.lifecycle.viewmodel.initializer
import androidx.lifecycle.viewmodel.viewModelFactory
import com.naleli.tbl.R
import com.naleli.tbl.ui.rememberAppContainer

@Composable
fun ProfileScreen(
    isEditMode: Boolean,
    onSaved: () -> Unit,
) {
    val container = rememberAppContainer()
    val viewModel: ProfileViewModel = viewModel(
        factory = viewModelFactory {
            initializer { ProfileViewModel(container.profileRepository) }
        },
    )
    val state by viewModel.state.collectAsState()

    Column(
        modifier = Modifier
            .fillMaxSize()
            .verticalScroll(rememberScrollState())
            .padding(20.dp),
        verticalArrangement = Arrangement.spacedBy(12.dp),
    ) {
        Text(
            text = if (isEditMode) stringResource(R.string.profile_edit) else stringResource(R.string.welcome_cta),
            style = MaterialTheme.typography.headlineSmall,
        )

        OutlinedTextField(
            value = state.firstName,
            onValueChange = viewModel::onFirstNameChange,
            label = { Text(stringResource(R.string.profile_first_name)) },
            modifier = Modifier.fillMaxWidth(),
            singleLine = true,
        )
        OutlinedTextField(
            value = state.surname,
            onValueChange = viewModel::onSurnameChange,
            label = { Text(stringResource(R.string.profile_surname)) },
            modifier = Modifier.fillMaxWidth(),
            singleLine = true,
        )
        OutlinedTextField(
            value = state.studentNumber,
            onValueChange = viewModel::onStudentNumberChange,
            label = { Text(stringResource(R.string.profile_student_number)) },
            modifier = Modifier.fillMaxWidth(),
            singleLine = true,
        )
        OutlinedTextField(
            value = state.email,
            onValueChange = viewModel::onEmailChange,
            label = { Text(stringResource(R.string.profile_email)) },
            modifier = Modifier.fillMaxWidth(),
            singleLine = true,
            keyboardOptions = androidx.compose.foundation.text.KeyboardOptions(keyboardType = KeyboardType.Email),
        )
        OutlinedTextField(
            value = state.phone,
            onValueChange = viewModel::onPhoneChange,
            label = { Text(stringResource(R.string.profile_phone)) },
            modifier = Modifier.fillMaxWidth(),
            singleLine = true,
            keyboardOptions = androidx.compose.foundation.text.KeyboardOptions(keyboardType = KeyboardType.Phone),
        )
        OutlinedTextField(
            value = stringResource(R.string.programme_name),
            onValueChange = {},
            label = { Text(stringResource(R.string.profile_programme)) },
            modifier = Modifier.fillMaxWidth(),
            singleLine = true,
            enabled = false,
        )
        OutlinedTextField(
            value = state.startDate.toString(),
            onValueChange = {},
            label = { Text(stringResource(R.string.profile_start_date)) },
            modifier = Modifier.fillMaxWidth(),
            singleLine = true,
            enabled = false,
        )

        state.validationError?.let {
            Text(it, color = MaterialTheme.colorScheme.error, style = MaterialTheme.typography.bodyMedium)
        }

        Button(
            onClick = { viewModel.save("digital-foundation", onSaved) },
            modifier = Modifier.fillMaxWidth(),
            enabled = !state.isSaving,
        ) {
            Text(stringResource(R.string.profile_save))
        }
    }
}
