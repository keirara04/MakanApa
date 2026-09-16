import SwiftUI

struct PreferenceView: View {
    @Environment(AppRouter.self) private var router
    @Environment(SoloViewModel.self) private var viewModel

    var body: some View {
        @Bindable var viewModel = viewModel

        Form {
            Section("Mood (optional)") {
                chipGrid(options: SoloViewModel.moodOptions, selection: $viewModel.selectedMoodTags)
            }

            Section("Cuisine (optional)") {
                chipGrid(options: SoloViewModel.cuisineOptions, selection: $viewModel.selectedCuisines)
            }

            Section("Budget") {
                Picker("Budget", selection: $viewModel.budgetMax) {
                    ForEach(SoloViewModel.budgetTiers, id: \.self) { tier in
                        Text(String(repeating: "RM", count: 1) + String(repeating: "$", count: tier)).tag(tier)
                    }
                }
                .pickerStyle(.segmented)
            }

            Section("How far?") {
                Picker("Distance", selection: $viewModel.maxDistanceKm) {
                    ForEach(SoloViewModel.distanceTiers, id: \.self) { tier in
                        Text("\(tier, specifier: "%.0f") km").tag(tier)
                    }
                }
                .pickerStyle(.segmented)
            }

            Section {
                Button {
                    viewModel.selectedMoodTags = []
                    viewModel.selectedCuisines = []
                    decideAndNavigate()
                } label: {
                    Text("🎲 Anything")
                        .frame(maxWidth: .infinity)
                }
            }

            Section {
                Button {
                    decideAndNavigate()
                } label: {
                    Text("MAKANAPA?")
                        .font(.headline)
                        .frame(maxWidth: .infinity)
                }
                .buttonStyle(.borderedProminent)
            }
        }
        .navigationTitle("What's the mood?")
    }

    private func decideAndNavigate() {
        viewModel.decide()
        router.push(.soloResult)
    }

    @ViewBuilder
    private func chipGrid(options: [String], selection: Binding<Set<String>>) -> some View {
        let columns = [GridItem(.adaptive(minimum: 100))]
        LazyVGrid(columns: columns, alignment: .leading, spacing: 8) {
            ForEach(options, id: \.self) { option in
                let isSelected = selection.wrappedValue.contains(option)
                Text(option.replacingOccurrences(of: "_", with: " "))
                    .padding(.horizontal, 12)
                    .padding(.vertical, 6)
                    .background(isSelected ? Color.accentColor : Color.secondary.opacity(0.15))
                    .foregroundStyle(isSelected ? Color.white : Color.primary)
                    .clipShape(Capsule())
                    .onTapGesture {
                        if isSelected {
                            selection.wrappedValue.remove(option)
                        } else {
                            selection.wrappedValue.insert(option)
                        }
                    }
            }
        }
    }
}
