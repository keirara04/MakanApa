import SwiftUI

struct PreferenceLoadingView: View {
    @ScaledMetric(relativeTo: .largeTitle) private var titleSize = 32

    let mood: String
    let budget: String
    let distance: String
    let onCancel: () -> Void

    var body: some View {
        VStack(spacing: 0) {
            (Text("Makan").foregroundStyle(Color.kicap)
             + Text("Apa?").foregroundStyle(Color.sambalRed))
                .font(.system(.title3, design: .rounded, weight: .heavy))
                .frame(minHeight: 44)
                .padding(.top, 8)

            GeometryReader { geometry in
                ScrollView {
                    VStack(spacing: 32) {
                        VStack(spacing: 24) {
                            AnimatedMakanMascot()

                            VStack(spacing: 12) {
                                Text("Finding your\nnext makan.")
                                    .font(.system(size: titleSize, weight: .bold, design: .rounded))
                                    .tracking(-0.8)
                                    .foregroundStyle(Color.kicap)
                                    .accessibilityAddTraits(.isHeader)
                                Text("Sekejap ya. We're looking for a spot\nthat fits your craving.")
                                    .font(.subheadline)
                                    .foregroundStyle(Color.kicap.opacity(0.65))
                            }
                            .multilineTextAlignment(.center)
                            .fixedSize(horizontal: false, vertical: true)
                        }

                        VStack(alignment: .leading, spacing: 16) {
                            Text("Your kind of makan")
                                .font(.system(.headline, design: .rounded))
                            Divider().overlay(Color.kicap.opacity(0.04))
                            Label(mood, systemImage: "heart")
                            Label(budget, systemImage: "banknote")
                            Label(distance, systemImage: "location")
                        }
                        .font(.subheadline)
                        .foregroundStyle(Color.kicap)
                        .frame(maxWidth: .infinity, alignment: .leading)
                        .padding(22)
                        .background(.white.opacity(0.55), in: RoundedRectangle(cornerRadius: 22))
                        .overlay {
                            RoundedRectangle(cornerRadius: 22)
                                .strokeBorder(Color.kicap.opacity(0.07), lineWidth: 1)
                        }

                        HStack(spacing: 10) {
                            ProgressView()
                                .tint(Color.pandan)
                                .accessibilityHidden(true)
                            Text("Looking for nearby spots…")
                                .font(.footnote)
                                .foregroundStyle(Color.kicap.opacity(0.65))
                        }
                        .accessibilityElement(children: .combine)

                        Button(action: onCancel) {
                            Text("Cancel")
                                .font(.makanBody(13))
                                .foregroundStyle(.secondary)
                        }
                    }
                    .frame(maxWidth: 420)
                    .padding(.horizontal, 32)
                    .padding(.vertical, 32)
                    .frame(maxWidth: .infinity)
                    .frame(minHeight: geometry.size.height)
                }
                .scrollIndicators(.hidden)
            }
        }
        .background(Color.nasiCream)
    }
}

#Preview {
    PreferenceLoadingView(mood: "Comfort", budget: "~RM20 per person", distance: "Within 2 km", onCancel: {})
}
