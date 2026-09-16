import SwiftUI

struct HomeView: View {
    @Environment(AppRouter.self) private var router

    var body: some View {
        VStack(spacing: 28) {
            HStack {
                Spacer()
                Image(systemName: "gearshape.fill")
                    .foregroundStyle(.secondary)
                    .opacity(0.4)
            }

            Image("Logo")
                .resizable()
                .scaledToFit()
                .frame(width: 160, height: 160)
                .clipShape(RoundedRectangle(cornerRadius: 32))

            Text(Copy.homeGreeting)
                .font(.makanDisplay(28))
                .foregroundStyle(Color.kicap)

            VStack(spacing: 16) {
                Button {
                    router.push(.soloPreferences)
                } label: {
                    VStack(spacing: 6) {
                        Text("👤").font(.system(size: 36))
                        Text("SOLO").font(.makanDisplay(20))
                        Text("Pick for me").font(.makanBody(14))
                    }
                    .foregroundStyle(.white)
                    .frame(maxWidth: .infinity)
                    .padding(.vertical, 24)
                }
                .background(Color.sambalRed)
                .clipShape(RoundedRectangle(cornerRadius: 24))

                VStack(spacing: 6) {
                    Text("👥").font(.system(size: 36))
                    Text("GENG").font(.makanDisplay(20))
                    Text("Settle for us").font(.makanBody(14))
                    Text("COMING SOON").font(.makanBody(11)).foregroundStyle(.secondary)
                }
                .foregroundStyle(Color.kicap.opacity(0.5))
                .frame(maxWidth: .infinity)
                .padding(.vertical, 24)
                .background(Color.kicap.opacity(0.06))
                .clipShape(RoundedRectangle(cornerRadius: 24))
            }
            .padding(.horizontal)

            Spacer()

            MascotLine(caption: Copy.homeTagline)
        }
        .padding()
        .frame(maxHeight: .infinity)
        .background(Color.nasiCream)
    }
}
