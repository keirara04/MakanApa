import SwiftUI

struct HomeView: View {
    @Environment(AppRouter.self) private var router

    var body: some View {
        VStack(spacing: 32) {
            Image("Logo")
                .resizable()
                .scaledToFit()
                .frame(width: 140, height: 140)
                .clipShape(RoundedRectangle(cornerRadius: 28))

            Text("Tak tahu nak makan apa?\nMakanApa decides.")
                .multilineTextAlignment(.center)
                .foregroundStyle(.secondary)

            VStack(spacing: 16) {
                Button {
                    router.push(.soloPreferences)
                } label: {
                    Label("SOLO — Just tell me what to eat", systemImage: "person.fill")
                        .frame(maxWidth: .infinity)
                        .padding()
                }
                .buttonStyle(.borderedProminent)

                Button {
                    // Group mode: not built in Phase 1
                } label: {
                    Label("WITH FRIENDS — Settle this for us", systemImage: "person.2.fill")
                        .frame(maxWidth: .infinity)
                        .padding()
                }
                .buttonStyle(.bordered)
                .disabled(true)
            }
            .padding(.horizontal)
        }
        .padding()
    }
}
